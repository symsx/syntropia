<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/../src/Configuration.php';
require __DIR__ . '/../src/Database.php';

use Syntropia\Configuration;
use Syntropia\Web\Authentification;

Authentification::demarrer($_SERVER);
(new Authentification())->deconnecter();

header('Location: ' . Configuration::base() . 'connexion.php');
exit;
