<?php

declare(strict_types=1);

function GetJsonProperty($jsonData, string $propertyName, &$propertyValue, $defaultValue = null) : bool
{
    $propertyFound = property_exists($jsonData, $propertyName);

    if ($propertyFound)
    {
        $propertyValue = $jsonData->$propertyName;
        return true;
    }

    $propertyValue = $defaultValue;
    return false;
}

function GetSprinklerOptionFromArray(array $options, int $sprinklerIndex, bool &$result, bool $negate = false) : bool
{
    $byteIndex = (int)($sprinklerIndex / 8);

    if ($byteIndex >= count($options))
        return false;

    $bitIsSet = pow(2, $sprinklerIndex % 8) & $options[$byteIndex] ? true : false;

    if ($negate)
        $result = !$bitIsSet;
    else
        $result = $bitIsSet;

    return true;
}

function UpdateSprinklerOptionInArray(array &$options, int $sprinklerIndex, bool $enableOption) : bool
{
    $byteIndex = (int)($sprinklerIndex / 8);

    if ($byteIndex >= count($options))
        return false;

    if ($enableOption)
        $options[$byteIndex] = $options[$byteIndex] | pow(2, $sprinklerIndex % 8);
    else
        $options[$byteIndex] = $options[$byteIndex] & 0xffffffff - pow(2, $sprinklerIndex % 8);

    return true;
}