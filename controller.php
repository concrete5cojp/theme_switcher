<?php
namespace Concrete\Package\ThemeSwitcher;

use Concrete\Core\Application\Application;
use Concrete\Core\Package\Package;
use Concrete5cojp\ThemeSwitcher\Console\Command\ApplyThemeCommand;
use Concrete5cojp\ThemeSwitcher\Console\Command\SwitchThemeCommand;

class Controller extends Package
{
    protected $appVersionRequired = '8.5.1';
    protected $pkgHandle = 'theme_switcher';
    protected $pkgVersion = '0.0.1';
    protected $pkgAutoloaderRegistries = [
        'src' => '\Concrete5cojp\ThemeSwitcher'
    ];

    public function getPackageName()
    {
        return t('Theme Switcher');
    }

    public function getPackageDescription()
    {
        return t('A command to apply theme to pages');
    }

    public function on_start()
    {
        if (Application::isRunThroughCommandLineInterface()) {
            /** @var \Concrete\Core\Console\Application $console */
            $console = $this->app->make('console');
            $console->add($this->app->make(ApplyThemeCommand::class));
            $console->add($this->app->make(SwitchThemeCommand::class));
        }
    }
}