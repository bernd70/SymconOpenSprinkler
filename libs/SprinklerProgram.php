<?php

declare(strict_types=1);

class SprinklerProgram
{
    const SCHEDULETYPE_Undefined = 0;
    const SCHEDULETYPE_Interval = 1;
    const SCHEDULETYPE_Weekday = 2;

    const STARTTIMETYPE_Repeating = 0;
    const STARTTIMETYPE_Fixed = 1;

    var $Index = -1;
    var $Name = "";
    var $Enabled = false;
    var $WeatherAdjusted = false;
    var $EvenDaysOnly = false;
    var $OddDaysOnly = false;
    var $ScheduleType = self::SCHEDULETYPE_Undefined;
    var $ScheduleDays = 0; // Bit 0..6: Mo - So
    var $IntervalDays = 0;
    var $IntervalDaysRemainder = 0;
    var $StartTimeType = self::STARTTIMETYPE_Fixed;

    public function InitFromProgramData(int $index, $programData)
    {
        $flags = $programData[0];
        $days0 = $programData[1];
        $days1 = $programData[2];
        $start = $programData[3];
        $duration = $programData[4];

        $this->Index = $index;
        $this->Name = $programData[5];

        $this->Enabled = $flags & 0x00000001;
        $this->WeatherAdjusted = $flags & 0x00000002;

        $this->EvenDaysOnly = $flags & 0x00000004;
        $this->OddDaysOnly = $flags & 0x00000008;

        switch ($flags & 0x00000030)
        {
            case 0:
               $this->ScheduleType = self::SCHEDULETYPE_Weekday;
               $this->ScheduleDays = $days0;
               break; 

            case 3:
                $this->ScheduleType = self::SCHEDULETYPE_Interval;
                $this->IntervalDays = $days0;
                $this->IntervalDaysRemainder = $days1;
                break; 

            default:
                $this->ScheduleType = self::SCHEDULETYPE_Undefined;
                break; 
        }

        $this->StartTimeType = $flags & 0x0000040 ? self::STARTTIMETYPE_Fixed : self::STARTTIMETYPE_Repeating;
    }

    public function Dump()
    {
        print(" - [$this->Index] $this->Name");

        if (!$this->Enabled)
            print(" [disabled]");

        print(" (" . $this->GetFlags() . ")\n");
    }

    public function GetFlags() : string
    {
        $flags = array();
        
        if ($this->WeatherAdjusted)
            array_push($flags, "WeatherAdjusted");

        if ($this->EvenDaysOnly)
            array_push($flags, "EvenDaysOnly");

        if ($this->OddDaysOnly)
            array_push($flags, "OddDaysOnly");

        switch ($this->ScheduleType)
        {
            case self::SCHEDULETYPE_Interval:
                array_push($flags, "Interval Schedule");
                break;

            case self::SCHEDULETYPE_Weekday:
                array_push($flags, "Weekday Schedule");
                break;

            case self::SCHEDULETYPE_Undefined:
                array_push($flags, "Undefined Schedule");
                break;
        }

        switch ($this->StartTimeType)
        {
            case self::STARTTIMETYPE_Fixed:
                array_push($flags, "Fixed Start Time");
                break;

            case self::STARTTIMETYPE_Repeating:
                array_push($flags, "Repeating Start Time");
                break;
        }
        
        return implode(", ", $flags);
    }
}


