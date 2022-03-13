<?php

declare(strict_types=1);

use CommunityTranslation\Entity\Locale;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @var Symfony\Component\Console\Input\InputInterface $input
 * @var Symfony\Component\Console\Output\OutputInterface $input
 * @var array $args
 */

$em = app(EntityManagerInterface::class);
if ($em->find(Locale::class, 'it_IT') === null) {
    $em->persist(new Locale('it_IT'));
}
if ($em->find(Locale::class, 'de_DE') === null) {
    $em->persist(new Locale('de_DE'));
}
$em->flush();

return 0;
