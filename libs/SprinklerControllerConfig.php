<?php

declare(strict_types=1);

class SprinklerControllerConfig
{
    const DATETIMEFORMAT_Default = "j.n.Y, H:i:s";

    function SetFromJson($jsonString = "")
    {
        $data = json_decode($jsonString, true);

        foreach ($data AS $key => $value)
            $this->{$key} = $value;
    }

    var $DeviceTime;
    var $NumberOfBoards = 0;
    var $OperationEnable = false;
    var $Sensor1Active = false;
    var $Sensor2Active = false;
    var $RainDelay = 0;

    function GetLocalDeviceTimeAsString(string $format = self::DATETIMEFORMAT_Default) : string
    {
        return $this->LocalToUtcTime($this->DeviceTime)->format($format);
    }

    function GetLocalRainDelayTimeAsString(string $format = self::DATETIMEFORMAT_Default) : string
    {
        if ($this->RainDelay == 0)
            return "";

        return $this->LocalToUtcTime($this->RainDelay)->format($format);
    }

    private function LocalToUtcTime(int $timestamp) : DateTime
    {
        $dateTime = new DateTime();

        $dateTime->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $dateTime->setTimestamp($timestamp);
        $dateTime->setTimezone(new DateTimeZone("UTC"));

        return $dateTime;
    }
}