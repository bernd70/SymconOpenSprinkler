<?php

declare(strict_types=1);

class SprinklerStation
{
    const KEY_Index = "Index";
    const KEY_Active = "Active";
    const KEY_Name = "Name";
    const KEY_Enabled = "Enabled";
    const KEY_WeatherAdjusted = "WeatherAdjusted";
    const KEY_Sensor1Enabled = "Sensor1Enabled";
    const KEY_Sensor2Enabled = "Sensor2Enabled";
    const KEY_Serialized = "Serialized";

    var $Index = -1;

    var $Active = false;

    var $Name = "";
    var $Enabled = false;
    var $WeatherAdjusted = false;
    var $Sensor1Enabled = false;
    var $Sensor2Enabled = false;
    var $Serialized = false;

    public function GetAsJson()
    {
        $jsonData = [];

        $jsonData[self::KEY_Index] = $this->Index;

        $jsonData[self::KEY_Active] = $this->Active;

        $jsonData[self::KEY_Name] = $this->Name;
        $jsonData[self::KEY_Enabled] = $this->Enabled;
        $jsonData[self::KEY_WeatherAdjusted] = $this->WeatherAdjusted;
        $jsonData[self::KEY_Sensor1Enabled] = $this->Sensor1Enabled;
        $jsonData[self::KEY_Sensor2Enabled] = $this->Sensor2Enabled;
        $jsonData[self::KEY_Serialized] = $this->Serialized;

        return $jsonData;
    }

    public function Dump()
    {
        print(" - [$this->Index] $this->Name");

        if (!$this->Enabled)
            print(" [disabled]");

        printf("\n");
    }
}


