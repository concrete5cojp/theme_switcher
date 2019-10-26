<?php

namespace Concrete5cojp\ThemeSwitcher\Console\Command;

use Concrete\Core\Console\Command;
use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Page\Page;
use Concrete\Core\Page\PageList;
use Concrete\Core\Page\Theme\Theme;
use Concrete\Core\Site\Service;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\Support\Facade\Site;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SwitchThemeCommand extends Command
{

    protected function configure()
    {
        $this
            ->setName('c5jp:switch-theme')
            ->setDescription('Switch theme for pages.')
            ->setAliases(['c5jp:switch:theme'])
            ->addEnvOption()
            ->addOption(
                'theme',
                't',
                InputOption::VALUE_REQUIRED,
                'The handle of the theme that you want to apply'
            )
            ->addOption(
                'parent',
                'p',
                InputOption::VALUE_REQUIRED,
                'Activate the theme to the site as default.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $app = Application::getFacadeApplication();

        $themeHandle = $input->getOption('theme');
        /** @var Theme $theme */
        $theme = Theme::getByHandle($themeHandle);
        if (!is_object($theme)) {
            throw new InvalidOptionException(sprintf('Theme %s not found.', $themeHandle));
        }

        $parentPagePath = $input->getOption('parent');
        $parent = Page::getByPath($parentPagePath);
        if (!is_object($parent) || $parent->isError()) {
            throw new InvalidOptionException(sprintf('Parent page %s not found.', $parentPagePath));
        }

        $list = new PageList();
        $list->ignorePermissions();
        $list->setSiteTreeToAll();
        $list->filterByPath($parentPagePath);
        $list->setPageVersionToRetrieve(PageList::PAGE_VERSION_RECENT);
        $pages = $list->getResults();

        if (count($pages) > 0) {
            $progressBar = new ProgressBar($output, count($pages));

            /** @var Page $page */
            foreach ($pages as $page) {
                $page->setTheme($theme);
                $progressBar->advance();
            }

            $progressBar->finish();
            $output->writeln('');
        }

        return true;
    }

}