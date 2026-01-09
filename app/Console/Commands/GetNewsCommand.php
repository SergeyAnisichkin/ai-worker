<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\NewsArticle;
use App\Models\User;
use App\Services\Chat\ChatService;
use App\Services\Support\TelegramService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetNewsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:get';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get news from sources';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            Log::info("Get news from sources - START ".Carbon::now()->toDateTimeString());
            $this->info('Get news from sources - START');
            $tgService = app(TelegramService::class);
//            $geminiService = app(GeminiService::class);
//            Log::info('Gemini resp ', [
//                'response' => $geminiService->generateContent('Explain how AI works in a few words'),
//            ]);

            $lastMessage = Message::query()
                ->where('role', 'user')
                ->whereNotNull('metadata')
                ->latest()
                ->first();
            $updateId = $lastMessage->metadata['update_id'] + 1;

            for ($i = 1; $i <= 1500; $i++) {
                $botMessages = $tgService->getUpdates($updateId);
                $this->info(json_encode($botMessages));
                Log::info('TG getUpdates '.Carbon::now()->toDateTimeString(), [
                    'messages' => json_encode($botMessages),
                ]);

                if (! empty($botMessages)) {
                    $updateId = $this->processBotMessages($botMessages);
                    $updateId++;
                }
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Command failed: ' . $e->getMessage());
            Log::error('Command failed, error: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    private function processBotMessages(array $botMessages): int
    {
        foreach ($botMessages as $item) {
            $message = new Message();
            $message->role = 'user';
            $message->content = $item['message']['text'];
            $message->metadata = $item;
            $message->save();

            $updateId = $item['update_id'];
            $this->executeUserCommand($message->content);
        }

        return $updateId;
    }

    private function executeUserCommand(string $text): void
    {
        $words = explode(' ', trim($text));

        if (in_array($words[0], ['new', 'нов', 'new', 'н', 'Н', 'n', 'N'])) {
            $this->executeNewsCommand();
        }
    }

    private function executeNewsCommand(): void
    {
        $newsArticles = [];

        foreach (range(1, 5) as $page) {
            $pageNewsArticles = $this->getNewsBlur($page);
            $pagePrepareNews = $this->prepareNewsArticles($pageNewsArticles);
            $filteredNews = $this->filterExistsNews($pagePrepareNews);

            if (empty($filteredNews)) {
                Log::info("Empty FilteredNews result, page=".$page);

                break;
            }

            $newsArticles += $filteredNews;
        }


        $success = NewsArticle::query()->insert($newsArticles);

        if (! $success) {
            Log::error("Error insert news, count=".count($newsArticles));

            return;
        }

        if (empty($newsArticles)) {
            app(TelegramService::class)->send('Нет новых новостей');

            return;
        }

        $this->processNewsLevels($newsArticles);
    }

    private function processNewsLevels(array $newsArticles): array
    {
        $newsList = [];

        foreach ($newsArticles as $article) {
            $newsList[$article['uuid']] = [
                'title' => json_decode($article['data'], true)['title'],
                'level' => 'none',
            ];
        }
        $count = count($newsArticles);
        $newsJsonText = json_encode($newsList, JSON_UNESCAPED_UNICODE);
        Log::info("NewsList for AI, count($count) - ".$newsJsonText);

        $user = User::find(3);
        $prompt = "Ты ИИ ассистент, который помогает оценивать важность новостей. Чётко следуй инструкциям дальше. Промежуточные мысли не нужны, нужен только готовый результат. В конце этого текста после тройного символа '#' начинается объект json как набор новостей, каждая новость имеет uuid, заголовок и level уровень важности новости. Оцени новости и замени none на значения high, mid, low на основе того, что самые важные новости это high должны касаться только крупных мировых происшествий, военные действия, техногенные и природные катастрофы, mid это если заголовок статьи о важном общественном тренде или явлении, например аналитика больших периодов и важных явлений, а все остальное это low. В ответе верни точно такой же объект с тремя символами '#', только с измененными уровнями level. ###";

        app(ChatService::class)->sendUserMessage(
            $user,
            $prompt . $newsJsonText,
        );

        return $newsList;
    }

    private function filterExistsNews(array $newsArticles): array
    {
        $existingNews = NewsArticle::query()
            ->whereIn('uuid', array_keys($newsArticles))
            ->get('uuid');

        foreach ($existingNews as $article) {
           unset($newsArticles[$article->uuid]);
        }

        return $newsArticles;
    }

    private function prepareNewsArticles(array $newsArticles): array
    {
        $pageNewsArticles = [];
        $now = Carbon::now()->toDateTimeString();

        foreach ($newsArticles as $article) {
            $pageNewsArticles[$article['story_hash']] = [
                'uuid' => $article['story_hash'],
                'published_at' => $article['story_date'],
                'url' => $article['story_permalink'],
                'data' => json_encode(['title' => $article['story_title']]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            Log::info('news story data: ',$pageNewsArticles[$article['story_hash']]);
        }

        return $pageNewsArticles;
    }

    private function getNewsBlur(int $page): array
    {
        $cookieFile = tempnam(sys_get_temp_dir(), 'newsblur_');

        try {
            $this->authenticate($cookieFile);

            return $this->fetchFeeds($cookieFile, $page);
        } catch (Exception $ex) {
            Log::error('getNewsBlur failed, error: '.$ex->getMessage());

            return [];
        } finally {
            if (file_exists($cookieFile)) {
                unlink($cookieFile);
            }
        }
    }

    private function markNewsAsRead(array $storyHashes): void
    {
        $cookieFile = tempnam(sys_get_temp_dir(), 'newsblur_');

        try {
            $this->authenticate($cookieFile);

            $this->markNewsBlurAsRead($cookieFile, $storyHashes);
        } catch (Exception $ex) {
            Log::error('markNewsBlurAsRead failed, error: '.$ex->getMessage());
        } finally {
            if (file_exists($cookieFile)) {
                unlink($cookieFile);
            }
        }
    }

    private function getNewsBlurOLD(Carbon $start, Carbon $end): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_COOKIEFILE => '',
            CURLOPT_COOKIEJAR => '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MyApp/1.0)',
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $this->postNewsBlur($ch,'login', ['username' => 'asvdev']);

        try {
//            $queryString = http_build_query([
//                'api_token' => 'GSR0a99bCM5Gxh8D4fezRa60QwR86p0PEMxZ0z9r',
//                'language' => 'ru',
//                'domains' => 'meduza.io',
//                'published_after' => $start->toDateTimeLocalString(),
//                'published_before' => $end->toDateTimeLocalString(),
//            ]);
//            Log::info('getNewsBlur queryString: '.$queryString);
//            $ch = curl_init(sprintf('%s?%s', 'https://www.newsblur.com/api/reader/feeds', $queryString));

            $url = 'https://www.newsblur.com/api/reader/feeds';
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

            $json = curl_exec($ch);
            curl_close($ch);

            $apiResult = json_decode($json, true);
            Log::info('getNewsBlur API results: ', ['results' => $apiResult]);

            return $apiResult['data'];
        } catch (Exception $e) {
            Log::error('getNewsBlur failed, error: '.$e->getMessage());
        }

        return [];
    }

    private function postNewsBlur($ch, string $method, array $params = []): array
    {
        try {
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://www.newsblur.com/api/'.$method,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($params),
            ]);

            $json = curl_exec($ch);
            curl_close($ch);

            $apiResult = json_decode($json, true);
            Log::info('postNewsBlur API results: ', ['results' => $apiResult]);

            return $apiResult['data'] ?? [];
        } catch (Exception $e) {
            Log::error('postNewsBlur failed, error: '.$e->getMessage());
        }

        return [];
    }

    private function authenticate(string $cookieFile): void
    {
        $ch = curl_init('https://www.newsblur.com/api/login');

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'username' => 'asvdev',
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MyApp/1.0)',
            CURLOPT_COOKIEJAR => $cookieFile,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);
        Log::info('Authentication result: ', ['result' => $result, 'http_code' => $httpCode]);

        if ($httpCode !== 200 || empty($result['authenticated'])) {
            throw new Exception('Authentication failed');
        }
    }

    private function fetchFeeds(string $cookieFile, int $page = 1): array
    {
        // meduza feed = 6045978
        $url = "https://www.newsblur.com/reader/feed/6045978?page=$page&read_filter=unread&include_story_content=false";

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MyApp/1.0)',
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
        ]);

        $json = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($json === false) {
            throw new Exception('cURL error: ' . $error);
        }

        $apiResult = json_decode($json, true);
        Log::info('Feeds API response', [
            'http_code' => $httpCode,
            'response' => $apiResult
        ]);

        if ($httpCode !== 200) {
            throw new Exception('API returned HTTP code: ' . $httpCode);
        }

        return $apiResult['stories'] ?? [];
    }

    private function markNewsBlurAsRead(string $cookieFile, array $storyHashes): array
    {
        $url = "https://www.newsblur.com/reader/mark_story_hashes_as_read";

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MyApp/1.0)',
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'story_hash' => $storyHashes,
            ]),
        ]);

        $json = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($json === false) {
            throw new Exception('cURL error: ' . $error);
        }

        $apiResult = json_decode($json, true);
        Log::info('MarkAsRead API response', [
            'http_code' => $httpCode,
            'response' => $apiResult
        ]);

        if ($httpCode !== 200) {
            throw new Exception('API returned HTTP code: ' . $httpCode);
        }

        return $apiResult ?? [];
    }

    private function getNews(Carbon $start, Carbon $end): array
    {
        try {
            $queryString = http_build_query([
                'api_token' => 'GSR0a99bCM5Gxh8D4fezRa60QwR86p0PEMxZ0z9r',
                'language' => 'ru',
                'domains' => 'meduza.io',
                'published_after' => $start->toDateTimeLocalString(),
                'published_before' => $end->toDateTimeLocalString(),
            ]);
            Log::info('getNews queryString: '.$queryString);
            $ch = curl_init(sprintf('%s?%s', 'https://api.thenewsapi.com/v1/news/all', $queryString));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $json = curl_exec($ch);
            curl_close($ch);

            $apiResult = json_decode($json, true);
            Log::info('getNews API results: ', ['results' => $apiResult]);

            return $apiResult['data'];
        } catch (Exception $e) {
            Log::error('getNews failed, error: '.$e->getMessage());
        }

        return [];
    }
}
