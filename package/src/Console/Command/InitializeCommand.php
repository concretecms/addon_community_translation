<?php
namespace Concrete\Package\CommunityTranslation\Src\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\Question;
use Exception;
use Zend\Http\Client;
use Concrete\Package\CommunityTranslation\Src\Git\Repository;
use Concrete\Package\CommunityTranslation\Src\Locale\Locale;

class InitializeCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('translate:initialize')
            ->addArgument('transifex-username', InputArgument::REQUIRED, "The Transifex username")
            ->addArgument('transifex-password', InputArgument::OPTIONAL, "The Transifex password")
            ->setDescription("Initializes the translation system by importing the data from GitHub and Transifex")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $rc = 0;
        try {
            $app = \Core::make('app');
            /* @var \Concrete\Core\Application\Application $app */

            $transifexUsername = $input->getArgument('transifex-username');
            $transifexPassword = $input->getArgument('transifex-password');
            if ($transifexPassword === null) {
                $question = new Question('Please enter the Transifex password for '.$transifexUsername.': ', '');
                $transifexPassword = trim($this->getHelper('question')->ask($input, $output, $question));
                if ($transifexPassword === '') {
                    $output->writeln('<error>Aborted</error>');

                    return 1;
                }
            }
            $client = new Client(
                '',
                array(
                    'adapter' => 'Zend\Http\Client\Adapter\Curl',
                    'curloptions' => array(
                        CURLOPT_SSL_VERIFYPEER => (bool) $app->make('config')->get('app.curl.verifyPeer'),
                    ),
                )
            );
            $client->setAuth($transifexUsername, $transifexPassword);

            $em = $app->make('community_translation/em');
            /* @var \Doctrine\ORM\EntityManager $em */

            $gitRepo = $app->make('community_translation/git');
            /* @var \Doctrine\ORM\EntityRepository $gitRepo */
            if ($gitRepo->findOneBy(array('grURL' => 'https://github.com/concrete5/concrete5-legacy.git')) === null) {
                $git = new Repository();
                $git->setName('concrete5 Core pre 5.7');
                $git->setPackage('');
                $git->setURL('https://github.com/concrete5/concrete5-legacy.git');
                $git->setDevBranch('master');
                $git->setDevVersion('dev-5.6');
                $git->setTagsFilter('< 5.7');
                $git->setWebRoot('web');
                $em->persist($git);
                $em->flush();
                $output->write(sprintf('Importing strings from %s (development and tagged versions)... ', $git->getName()));
                $app->make('community_translation/git/importer')->import($git);
                $output->writeln("<info>done</info>");
            }
            if ($gitRepo->findOneBy(array('grURL' => 'https://github.com/concrete5/concrete5.git')) === null) {
                $git = new Repository();
                $git->setName('concrete5 Core from 5.7');
                $git->setPackage('');
                $git->setURL('https://github.com/concrete5/concrete5.git');
                $git->setDevBranch('develop');
                $git->setDevVersion('dev-5.7');
                $git->setTagsFilter('>= 5.7');
                $git->setWebRoot('web');
                $em->persist($git);
                $em->flush();
                $output->write(sprintf('Importing strings from %s (development and tagged versions)... ', $git->getName()));
                $app->make('community_translation/git/importer')->import($git);
                $output->writeln("<info>done</info>");
            }

            $output->write('Retrieve list of locales... ');
            $client->setUri('https://www.transifex.com/api/2/project/concrete5/languages/');
            $client->setMethod('GET');
            $response = $client->send();
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                throw new Exception($response->getReasonPhrase());
            }
            $txLocales = @json_decode($response->getBody(), true);
            if (!is_array($txLocales)) {
                throw new Exception('Failed to decode response');
            }
            $localeIDs = array('en_US');
            foreach ($txLocales as $txLocale) {
                $id = $txLocale['language_code'];
                if ($id !== 'en' && $id !== 'en_US') {
                    $localeIDs[] = $id;
                }
            }
            $output->writeln("<info>done (".count($localeIDs)." found).</info>");

            $output->write('Retrieve list of resources... ');
            $client->setUri('https://www.transifex.com/api/2/project/concrete5/resources/');
            $client->setMethod('GET');
            $response = $client->send();
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                throw new Exception($response->getReasonPhrase());
            }
            $txResources = @json_decode($response->getBody(), true);
            if (!is_array($txResources)) {
                throw new Exception('Failed to decode response');
            }
            $resources = array();
            foreach ($txResources as $txResource) {
                $slug = $txResource['slug'];
                if (preg_match('/^core-dev-(\d+)$/', $slug, $m)) {
                    $version = 'dev-'.implode('.', str_split($m[1], 1));
                } elseif (preg_match('/^core-(\d+)$/', $slug, $m)) {
                    $version = implode('.', str_split($m[1], 1));
                } else {
                    throw new Exception(sprintf('Failed to decode resource slug %s', $slug));
                }
                $resources[$slug] = $version;
            }
            krsort($resources);
            $output->writeln("<info>done (".count($resources)." found).</info>");

            $localeRepo = $app->make('community_translation/locale');
            /* @var \Doctrine\ORM\EntityRepository $localeRepo */
            $translationsImporter = $app->make('community_translation/translation/importer');
            /* @var \Concrete\Package\CommunityTranslation\Src\Translation\Importer $translationsImporter */
            foreach ($localeIDs as $localeID) {
                $output->writeln('Working on '.$localeID);
                $output->write(sprintf(' - checking entity... ', $localeID));
                $locale = $localeRepo->find($localeID);
                if ($locale === null) {
                    $locale = Locale::createForLocaleID($localeID);
                    $msg = 'created';
                } else {
                    $msg = 'updated';
                }
                $locale->setIsSource($localeID === 'en_US');
                $locale->setIsApproved(true);
                $em->persist($locale);
                $em->flush();
                $output->writeln("<info>$msg.</info>");
                if ($localeID !== 'en_US') {
                    foreach ($resources as $slug => $version) {
                        $output->write(sprintf(' - fetching translations for %s... ', $version));
                        $client->setUri('https://www.transifex.com/api/2/project/concrete5/resource/'.$slug.'/translation/'.$localeID.'/');
                        $client->setMethod('GET');
                        $response = $client->send();
                        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                            throw new Exception($response->getReasonPhrase());
                        }
                        $data = @json_decode($response->getBody(), true);
                        if (!is_array($data) || !isset($data['mimetype']) || $data['mimetype'] !== 'text/x-po' || !isset($data['content']) || !is_string($data['content']) || $data['content'] === '') {
                            throw new Exception('Failed to decode response');
                        }
                        $translations = \Gettext\Translations::fromPoString($data['content']);
                        if (count($translations) < 100) {
                            throw new Exception('Too few translations downloaded');
                        }
                        $output->writeln('<info>done ('.count($translations).' strings).</info>');
                        /* @var \Gettext\Translations $translations */
                        $txPlurals = $translations->getPluralForms();
                        $count = isset($txPlurals) ? $txPlurals[0] : null;
                        if ($count !== $locale->getPluralCount()) {
                            $output->write(sprintf(' - fixing plural count (from %1$s to %2$s)... ', $count, $locale->getPluralCount()));
                            $translations->setLanguage($locale->getID());
                            $output->writeln('<info>done.</info>');
                        }
                        $output->write(' - saving... ');
                        $translationsImporter->import($translations, $locale, 2);
                        $output->writeln('<info>done.</info>');
                    }
                }
            }
        } catch (Exception $x) {
            $output->writeln('<error>'.$x->getMessage().'</error>');
            $rc = 1;
        }

        return $rc;
    }
}
