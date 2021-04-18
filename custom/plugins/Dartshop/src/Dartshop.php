<?php declare(strict_types=1);

namespace Dartshop;

use Shopware\Core\Framework\Plugin;
use Shopware\Storefront\Framework\ThemeInterface;

class Dartshop extends Plugin implements ThemeInterface
{
    public function getThemeConfigPath(): string
    {
        return 'theme.json';
    }
}