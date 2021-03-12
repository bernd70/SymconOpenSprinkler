<?php

declare(strict_types=1);

class SprinklerStation
{
    var $Index = -1;

    var $Active = false;

    var $Name = "";
    var $Enabled = false;
    var $ScheduledTime = 0;
    var $ScheduledDuration = 0;
    var $WeatherAdjusted = false;
    var $Sensor1Enabled = false;
    var $Sensor2Enabled = false;
    var $Serialized = false;

    function __construct(int $index, string $name, bool $enabled, bool $active)
    {
        $this->Index = $index;
        $this->Name = $name;
        $this->Enabled = $enabled;
        $this->Active = $active;
    }

    public function InitFromJson($jsonString)
    {
        $data = json_decode($jsonString, true);

        foreach ($data AS $key => $value)
            $this->{$key} = $value;
    }

    public function SetOptions(bool $weatherAdjusted, bool $sensor1Enabled, bool $sensor2Enabled, bool $serialized)
    {
        $this->WeatherAdjusted = $weatherAdjusted;
        $this->Sensor1Enabled = $sensor1Enabled;
        $this->Sensor2Enabled = $sensor2Enabled;
        $this->Serialized = $serialized;
    }

    public function SetScheduled(bool $scheduled, int $duration, int $time)
    {
        if ($scheduled)
        {
            $this->ScheduledTime = $time;
            $this->ScheduledDuration = $duration;
        }
        else
        {
            $this->ScheduledTime = 0;
            $this->ScheduledDuration = 0;
        }
    }

    public function Dump()
    {
        print(" - [$this->Index] $this->Name");

        if (!$this->Enabled)
            print(" [disabled]");

        printf("\n");
    }
}

