<?php

declare(strict_types=1);

class SprinklerControllerConfig
{
    const DATETIMEFORMAT_Default = "j.n.Y, H:i:s";

    function __construct($data = null)
    {
        if ($data != null)
        {
            foreach ($data AS $key => $value)
                $this->{$key} = $value;
        }
    }

    const SENSORTYPE_Inactive = 0;
    const SENSORTYPE_Rain = 1;
    const SENSORTYPE_Flow = 2;
    const SENSORTYPE_Soil = 3;
    const SENSORTYPE_Program = 240;

    const WEATHERMETHOD_Manual = 0;
    const WEATHERMETHOD_Zimmerman = 1;
    const WEATHERMETHOD_AutoDelayOnRain = 2;
    const WEATHERMETHOD_Evapotranspiration = 3;

    var $DeviceTime;
    var $NumberOfBoards = 0;
    var $OperationEnable = false;
    var $Sensor1Type = self::SENSORTYPE_Inactive;
    var $Sensor2Type = self::SENSORTYPE_Inactive;
    var $RainDelay = 0;
    var $WaterLevel = 100;
    var $WeatherMethod = self::WEATHERMETHOD_Manual;

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

    private function SensorTypeToString(int $type) : string
    {
        switch ($type)
        {
            case self::SENSORTYPE_Inactive:
                return "Inactive";

            case self::SENSORTYPE_Rain:
                return "Rain";

            case self::SENSORTYPE_Flow:
                return "Flow";

            case self::SENSORTYPE_Soil:
                return "Soil";

            case self::SENSORTYPE_Program:
                return "Program";

            default:
                return "Unknown/Other";
        }
    }

    public function GetSensor1Type() : string
    {
        return $this->SensorTypeToString($this->Sensor1Type);
    }

    public function GetSensor2Type() : string
    {
        return $this->SensorTypeToString($this->Sensor2Type);
    }

    public function GetWeatherMethod() : string
    {
        switch ($this->WeatherMethod)
        {
            case self::WEATHERMETHOD_Manual:
                return "Manual";

            case self::WEATHERMETHOD_Zimmerman:
                return "Zimmerman";

            case self::WEATHERMETHOD_AutoDelayOnRain:
                return "Auto Delay on Rain";

            case self::WEATHERMETHOD_Evapotranspiration:
                return "Evapotranspiration";

            default:
                return "Unknown/Other";
        }
    }
}