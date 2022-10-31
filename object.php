#!/usr/bin/env php
<?php

$code='
$some = [
  "interface" => "web",
  "event" => "change",
  "type" => ["tckn", "vkn", "ykn"],
  "prefix" => "id-number",
  "target" => "id-number-name",
  "errorType" => "pg",
  "data" => [
    "data" => [],
    "idNumber" => $data["id_number"] ?? null,
    "idNumberName" => $view["general"]->hideString($data["id_number_name"]) ?? null,
    "birthDate" => $data["id_number_birth_date"] ?? null,
    "adminCollateralIdTextTrigger" => "data-id-number",
    "adminCollateralIdNameTextTrigger" => "data-id-number-name",
  ],
  "toggle" => [
    "group" => "id-number",
    "target" => "id-number-tckn",
  ],
  "readonly" => [
    "idNumber" => false,
    "idNumberName" => true,
  ],
  "birthDateHidden" => (!empty($data) ? (11 == strlen($data["id_number"]) ? false : true) : true),
  "formGroupSize" => "form-group-sm",
]
';
// $matches = [];
// $oldCode = $code;
// // just for variables: ((?<!\() | \()(\$.*?)(=?,)
// preg_match('/((?<!\() | \()(\$.*?)(=?,)/', $oldCode, $matches, PREG_UNMATCHED_AS_NULL);
// while ($matches) {
//     $sanitizedString = str_replace('$', '', $matches[0]);
//     $oldCode = str_replace($matches[0], "changed", $code);
//     $code = str_replace($matches[0], "'$sanitizedString'", $code);
//     $matches = [];
//     preg_match('/((?<!\() | \()(\$.*?)(=?,)/', $oldCode, $matches, PREG_UNMATCHED_AS_NULL);
// }

// just for conditions: ((?<!\() | \()(\$|\(.*?)(=?,)
// preg_match('/((?<!\() | \()(\$|\(.*?)(=?,)/', $code, $matches, PREG_UNMATCHED_AS_NULL);
// while ($matches) {
//     $sanitizedString = str_replace('$', '', $matches[2]);
//     $code = str_replace($matches[2], "'$sanitizedString'", $code);
//     $matches = [];
//     preg_match('/((?<!\() | \()(\$|\(.*?)(=?,)/', $code, $matches, PREG_UNMATCHED_AS_NULL);
// }


// Change the ! operator with twig not operator
// $code = str_replace('!', 'not ', $code);

// print_r($code);

//print_r(json_encode($code));

$tokens = token_get_all($code);
print_r($tokens);
