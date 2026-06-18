<?php
require 'vendor/autoload.php';
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

$e = new CustomUserMessageAccountStatusException('VOTRE_COMPTE_EN_ATTENTE');
echo $e->getMessageKey() . "\n";
