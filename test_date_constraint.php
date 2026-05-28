<?php
require 'vendor/autoload.php';

use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;

$validator = Validation::createValidator();

$constraint = new LessThanOrEqual([
    'value' => '-18 years',
    'message' => 'Too young'
]);

// Try an invalid date (10 years ago)
$dateInvalid = new \DateTime('-10 years');
$violations1 = $validator->validate($dateInvalid, $constraint);

// Try a valid date (20 years ago)
$dateValid = new \DateTime('-20 years');
$violations2 = $validator->validate($dateValid, $constraint);

echo "Invalid date violations count: " . count($violations1) . "\n";
echo "Valid date violations count: " . count($violations2) . "\n";
