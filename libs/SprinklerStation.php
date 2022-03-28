<?php

declare(strict_types=1);

class SprinklerStation
{
    const STATUS_Unknown = 0;
    const STATUS_Deactivated = 1;
    const STATUS_Idle = 2;
    const STATUS_Scheduled = 3;
    const STATUS_Running = 4;

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

    public function InitFromJson(string $jsonString)
    {
        $data = json_decode($jsonString, true);

        foreach ($data AS $key => $value)
            $this->{$key} = $value;
    }

    public function SetOptions(bool $weatherAdjusted, $sensor1Enabled, $sensor2Enabled, bool $serialized)
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

    public static function TranslateToStatus(bool $enabled, bool $active, int $scheduledTime) : int
    {
        if (!$enabled)
            return SprinklerStation::STATUS_Deactivated;

        if ($active)
            return SprinklerStation::STATUS_Running;

        if ($scheduledTime != 0)
            return SprinklerStation::STATUS_Scheduled;

        return SprinklerStation::STATUS_Idle;
    }

    public function GetStatus() : int
    {
        return SprinklerStation::TranslateToStatus($this->Enabled, $this->Active, $this->ScheduledTime);
    }

    public function Dump()
    {
        print(" - [$this->Index] $this->Name");

        if (!$this->Enabled)
            print(" [disabled]");

        printf("\n");
    }
}

