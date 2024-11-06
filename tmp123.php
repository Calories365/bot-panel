<?php
$arr = [
    [123, 3462],
    [1232, 341],
    [123, 346],
    [123, 3436],
];
$params = ["Калории", "Белки", "Жиры", "Углеводы"];
function generateTable($title, $quantity, $dataArray, $params) {
    $title = $title . "\n\n";
    $quantity .="г";
    $quantity = str_pad(' ' . $quantity, 8, " ", STR_PAD_RIGHT);
    $header = "| Параметр   | 100г  |" . $quantity. "|\n";
    $partition = "|------------|-------|-------|\n";
    $body = "";
    foreach ($dataArray as $key => $subArray) {
        $body .=
            "|". str_pad(' ' . $params[$key], mb_strlen($params[$key], "UTF-8")+12, " ", STR_PAD_RIGHT) .
            "|". str_pad(' ' . $subArray[0], 7, " ", STR_PAD_RIGHT) .
            "|". str_pad(' ' . $subArray[1], 7, " ", STR_PAD_RIGHT) . "|\n";
    }
    $body .= "";
    return $title . $header . $partition . $body;
}

$text = generateTable("Творог", 1250,$arr, $params );
