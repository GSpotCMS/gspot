<?php

namespace GSpot\UserPlugins\JavabeanAdmin;

use GSpot\Plugin\AbstractPlugin;

class JavabeanAdminPlugin extends AbstractPlugin
{
    public function onAppBoot(mixed &$app): void
    {
        // Register the JavaBean theme engine with the admin panel
    }

    public function getActivePreset(): string
    {
        return $this->config['config']['active_preset'] ?? 'javabean-classic';
    }

    public function getDensity(): string
    {
        return $this->config['config']['density'] ?? 'comfy';
    }

    public function getCustomCss(): string
    {
        return $this->config['config']['custom_css'] ?? '';
    }

    public function getStyling(): array
    {
        return $this->config['config']['styling'] ?? [];
    }

    public function buildAdminCss(): string
    {
        require_once __DIR__ . '/classes/JavaBeanPresetRegistry.php';
        require_once __DIR__ . '/classes/JavaBeanFontCatalog.php';
        require_once __DIR__ . '/classes/JavaBeanThemeEngine.php';

        $engine = new \GSpot\UserPlugins\JavabeanAdmin\JavaBeanThemeEngine();
        return $engine->buildCss([
            'enabled' => true,
            'active_preset' => $this->getActivePreset(),
            'density' => $this->getDensity(),
            'custom_css' => $this->getCustomCss(),
            'styling' => $this->getStyling(),
        ]);
    }
}
