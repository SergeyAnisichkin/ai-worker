<?php

namespace App\Contracts\Agent;

/**
 * Interface PluginManagerInterface
 *
 * Plugin Manager for configuration and lifecycle management
 * This interface defines methods for managing plugins in the system,
 * including retrieving plugin information, updating configurations,
 * testing connections, and managing plugin states.
 */
interface PluginManagerInterface
{
    /**
     * Get all plugins with their configuration info
     *
     * @return array
     */
    public function getAllPluginsInfo(): array;

    /**
     * Get single plugin information
     *
     * @param CommandPluginInterface $plugin
     * @return array
     */
    public function getPluginInfo(CommandPluginInterface $plugin): array;

    /**
     * Update plugin configuration
     *
     * @param string $pluginName
     * @param array $config
     * @return array
     */
    public function updatePluginConfig(string $pluginName, array $config): array;

    /**
     * Test plugin connection/functionality
     *
     * @param CommandPluginInterface $plugin
     * @return boolean
     */
    public function testPluginConnection(CommandPluginInterface $plugin): bool;

    /**
     * Test all plugins
     *
     * @return array
     */
    public function testAllPlugins(): array;

    /**
     * Get plugin configuration schema (for frontend forms)
     *
     * @param string $pluginName
     * @return array|null
     */
    public function getPluginConfigSchema(string $pluginName): ?array;

    /**
     * Enable/disable plugin
     *
     * @param string $pluginName
     * @param boolean $enabled
     * @return array
     */
    public function setPluginEnabled(string $pluginName, bool $enabled): array;

    /**
     * Reset plugin to default configuration
     *
     * @param string $pluginName
     * @return array
     */
    public function resetPluginConfig(string $pluginName): array;

    /**
     * Get plugin statistics
     *
     * @return array
     */
    public function getPluginStatistics(): array;

    /**
     * Get plugins status for health check
     *
     * @return array
     */
    public function getHealthStatus(): array;

    /**
     * Get plugin instance with ensured configuration
     * This method should be used by command executors
     */
    public function getConfiguredPlugin(string $pluginName): ?CommandPluginInterface;
}
