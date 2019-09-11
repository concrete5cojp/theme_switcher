<?php

namespace Concrete5cojp\ThemeSwitcher\Console\Command;

use Concrete\Core\Console\Command;
use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Page\Theme\Theme;
use Concrete\Core\Site\Service;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\Support\Facade\Site;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SwitchThemeCommand extends Command
{

    protected function configure()
    {
        $this
            ->setName('c5jp:switch-theme')
            ->setAliases(['c5jp:switch:theme'])
            ->addEnvOption()
            ->addOption(
                'theme',
                't',
                InputOption::VALUE_REQUIRED,
                'The handle of the theme that you want to apply'
            )
            ->addOption(
                'activate',
                'a',
                InputOption::VALUE_NONE,
                'Activate the theme to the site as default.'
            )
            ->addOption(
                'site',
                's',
                InputOption::VALUE_OPTIONAL,
                'The handle of the site that you want to apply to. If you keep this value empty, the new theme will be applied only for the default site.'
            )
            ->addOption(
                'from',
                'f',
                InputOption::VALUE_OPTIONAL,
                'The handle of the theme. The new theme will be applied only for pages that applied this old theme.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $app = Application::getFacadeApplication();

        $toThemeHandle = $input->getOption('theme');
        /** @var Theme $toTheme */
        $toTheme = Theme::getByHandle($toThemeHandle);
        if (!is_object($toTheme)) {
            throw new InvalidOptionException(sprintf('Theme %s not found.', $toThemeHandle));
        }

        /** @var Service $siteService */
        $siteService = $app->make(Service::class);
        $siteHandle = $input->getOption('site');
        if ($siteHandle) {
            $site = $siteService->getByHandle($siteHandle);
        } else {
            $site = $siteService->getDefault();
        }
        if (!is_object($site)) {
            throw new InvalidOptionException(sprintf('Site %s not found.', $siteHandle));
        }

        $fromThemeHandle = $input->getOption('from');
        if ($fromThemeHandle) {
            /** @var Theme $fromTheme */
            $fromTheme = Theme::getByHandle($fromThemeHandle);
            if (!is_object($fromTheme)) {
                throw new InvalidOptionException(sprintf('Theme %s not found.', $fromTheme));
            }
        }

        if ($input->getOption('activate')) {
            /** @var EntityManagerInterface $em */
            $em = $app->make(EntityManagerInterface::class);
            $site->setThemeID($toTheme->getThemeID());
            $em->persist($site);
            $em->flush();
        }

        $treeIDs = [0];
        foreach($site->getLocales() as $locale) {
            $tree = $locale->getSiteTree();
            if (is_object($tree)) {
                $treeIDs[] = $tree->getSiteTreeID();
            }
        }

        /** @var Connection $conn */
        $conn = $app->make(Connection::class);
        $qb = $conn->createQueryBuilder();
        $qb->update('CollectionVersions', 'cv')
            ->set('cv.pThemeID', ':toThemeID')
            ->where(
                $qb->expr()->in(
                    'cv.cID',
                    $conn->createQueryBuilder()
                        ->select('p.cID')
                        ->from('Pages', 'p')
                        ->where($qb->expr()->in('p.siteTreeID', $treeIDs))
                        ->andWhere($qb->expr()->gt('p.ptID', 0))
                        ->andWhere($qb->expr()->eq('p.cIsTemplate', 0))
                        ->execute()
                )
            )
            ->andWhere($qb->expr()->gt('cv.pTemplateID', 0))
            ->setParameter('toThemeID', $toTheme->getThemeID())
            ->setParameter('treeIDs', $treeIDs)
        ;
        if (isset($fromTheme)) {
            $qb->andWhere('cv.pThemeID = :fromThemeID')
                ->setParameter('fromThemeID', $fromTheme->getThemeID());
        }
        $rows = $qb->execute();

        if (isset($fromTheme)) {
            $output->writeln(sprintf('Successfully applied %1$s theme from %2$s for %3$s site. %4$d pages updated.', $toTheme->getThemeDisplayName(), $fromTheme->getThemeDisplayName(), $site->getSiteName(), $rows));
        } else {
            $output->writeln(sprintf('Successfully applied %1$s theme for %2$s site. %3$d pages updated.', $toTheme->getThemeDisplayName(), $site->getSiteName(), $rows));
        }

        return true;
    }

}