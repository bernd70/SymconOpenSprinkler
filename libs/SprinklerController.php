<?php

declare(strict_types=1);

require_once "SprinklerHelper.php";
require_once "SprinklerStation.php";
require_once "SprinklerProgram.php";

class SprinklerController
{
    var $stations = array();
    var $programs = array();

    var $host;
    var $passwordHash = "";
    var $retries = 3;

    var $logCallback;
    var $logLevel = 0;

    const LOGLEVEL_STANDARD = 5;
    const LOGLEVEL_DEBUG = 9;

    const LOG_FATAL = 0;
    const LOG_ERROR = 1;
    const LOG_WARNING = 2;
    const LOG_INFO = 3;
    const LOG_DEBUG = 9;

    var $DeviceTime;
    var $NumberOfBoards = 0;
    var $OperationEnable = false;
    var $Sensor1Active = false;
    var $Sensor2Active = false;
    var $RainDelay = 0;

    public function Init($host, $password, $retries = 3)
    {
        $this->host = $host;
        $this->passwordHash = md5($password);
        $this->retries = $retries;
    }

    public function SetLogCallback(callable $logCallback, int $logLevel)
    {
        $this->logCallback = $logCallback;
        $this->logLevel = $logLevel;
    }

    public function Read(&$error) : bool
    {
        // Get all Sprinkler Data
        if (!$this->ExecuteCommand("ja", "", $jsonData, $error))
            return false;

        if (!property_exists($jsonData, "settings"))
        {
            $error = "Section [settings] missing from json";
            return false;
        }

        if (!property_exists($jsonData, "stations"))
        {
            $error = "Section [stations] missing from json";
            return false;
        }

        if (!property_exists($jsonData, "status"))
        {
            $error = "Section [status] missing from json";
            return false;
        }

        if (!property_exists($jsonData, "programs"))
        {
            $error = "Section [programs] missing from json";
            return false;
        }

        if (!$this->InitSettingsFromJson($jsonData->settings, $error))
            return false;

        if (!$this->InitStationsFromJson($jsonData->stations, $jsonData->status, $error))
            return false;

        if (!$this->InitProgramsFromJson($jsonData->programs, $error))
            return false;

        return true;
    }

    public function EnableStation(int $stationIndex, bool $enable, &$error) : bool
    {
        return $this->ExecuteCommand("cs", "d$stationIndex=$enable", $jsonData, $error);
    }

    public function RunStation(int $stationIndex, bool $enable, int $duration, &$error) : bool
    {
        return $this->ExecuteCommand("cm", "sid=$stationIndex&en=$enable&t=$duration", $jsonData, $error);
    }

    public function ExecuteCustomCommand(string $cmd, string $params, &$jsonData, &$error) : bool
    {
        return $this->ExecuteCommand($cmd, $params, $jsonData, $error);
    }

    private function ExecuteCommand(string $cmd, string $params, &$jsonData, &$error) : bool
    {
        if (!isset($this->host) || $this->host == "")
        {
            $error = "Host not set";
            return false;
        }

        $url = "http://$this->host/$cmd?pw=$this->passwordHash";
        if ($params != "")
            $url .= "&" . $params;
    
        $this->Log(self::LOG_DEBUG, __FUNCTION__, "Url=$url");
        
        $attempt = 1;

        while ($attempt <= $this->retries)
        {
            $request = curl_init($url);
            
            curl_setopt($request, CURLOPT_USERAGENT, "SymconOpenSprinkler");
            curl_setopt($request, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($request, CURLOPT_TIMEOUT, 5);
            curl_setopt($request, CURLOPT_POST, true);
            curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);

            $result = curl_exec($request);
            $status = curl_getinfo($request, CURLINFO_HTTP_CODE);

            $this->Log(self::LOG_DEBUG, __FUNCTION__, "Attempt $attempt: Result=$result, Status=$status");
            
            curl_close($request);
            
            if ($result !== false && $status == 200 /* HTTP_OK */)
                break;

            $this->Log(self::LOG_DEBUG, __FUNCTION__, "Web Request Error $error on attempt $attempt)");
            $attempt++;

            sleep(1);
        }

        if ($attempt > $this->retries)
        {
            if ($result === false)
                $error = "Empty or no result received (status=$status)";
            else if ($status != 200)
                $error = "HTTP error code $status received";
            else
                $error = "Unknown error";

            return false;
        }
    
        $jsonData = json_decode($result);
    
        if (property_exists($jsonData, "result"))
        {
            switch ($jsonData->result)
            {
                case 1:
                    return true;
    
                case 2:
                    $error = "Unauthorized";
                    return false;
    
                case 3:
                    $error ="Mismatch";
                    return false;
    
                case 16:
                    $error = "Data Missing";
                    return false;
                    
                case 17: 
                    $error = "Out of Range";
                    return false;
                    
                case 18:
                    $error = "Data Format Error";
                    return false;
                    
                case 19:
                    $error = "RF code error";
                    return false;
                    
                case 32:
                    $error = "Page Not Found";
                    return false;
                    
                case 48: 
                    $error = "Not permitted";
                    return false;
            }
        }
    
        return true;
    }    

    private function InitSettingsFromJson($jsonData, &$error) : bool
    {
        GetJsonProperty($jsonData, "devt", $this->DeviceTime, 0);
        GetJsonProperty($jsonData, "nbrd", $this->NumberOfBoards, 1);
        GetJsonProperty($jsonData, "en", $this->OperationEnable, false);
        GetJsonProperty($jsonData, "sn1", $this->Sensor1Active, false);
        GetJsonProperty($jsonData, "sn2", $this->Sensor2Active, false);
        GetJsonProperty($jsonData, "rdst", $this->RainDelay, false);

        return true;
    }

    private function InitStationsFromJson($jsonDataStations, $jsonDataStatus, &$error) : bool
    {
        unset($this->stations);
        $this->stations = array();

        $optionValue = false;

        if (!property_exists($jsonDataStations, "snames"))
        {
            $error = "Section [stations/snames] missing from json";
            return false;
        }

        if (!property_exists($jsonDataStatus, "sn"))
        {
            $error = "Section [status/sn] missing from json";
            return false;
        }

        $jsonDataDisabled = null;
        if (!GetJsonProperty($jsonDataStations, "stn_dis", $jsonDataDisabled))
        {
            $error = "Section [stations/stn_dis] missing from json";
            return false;
        }
        $jsonDataIgnoreRain = null;
        if (!GetJsonProperty($jsonDataStations, "ignore_rain", $jsonDataIgnoreRain))
        {
            $error = "Section [stations/ignore_rain] missing from json";
            return false;
        }
        $jsonDataIgnoreSensor1 = null;
        if (!GetJsonProperty($jsonDataStations, "ignore_sn1", $jsonDataIgnoreSensor1))
        {
            $error = "Section [stations/ignore_sn2] missing from json";
            return false;
        }
        $jsonDataIgnoreSensor2 = null;
        if (!GetJsonProperty($jsonDataStations, "ignore_sn2", $jsonDataIgnoreSensor2))
        {
            $error = "Section [stations/ignore_sn2] missing from json";
            return false;
        }
        $jsonDataSerialized = null;
        if (!GetJsonProperty($jsonDataStations, "stn_seq", $jsonDataSerialized))
        {
            $error = "Section [stations/stn_seq] missing from json";
            return false;
        }                                

        foreach ($jsonDataStations->snames as $key => $stationName)
        {
            $station = new SprinklerStation();

            $station->Index = $key;
            $station->Name = $stationName;
            
            GetSprinklerOptionFromArray($jsonDataDisabled, $key, $station->Enabled, true);
            GetSprinklerOptionFromArray($jsonDataIgnoreRain, $key, $station->WeatherAdjusted, true);
            GetSprinklerOptionFromArray($jsonDataIgnoreSensor1, $key, $optionValue, true);
            GetSprinklerOptionFromArray($jsonDataIgnoreSensor2, $key, $optionValue, true);
            GetSprinklerOptionFromArray($jsonDataSerialized, $key, $station->Serialized);

            $station->Active = $key < count($jsonDataStatus->sn) ? $jsonDataStatus->sn[$key] : false;

            array_push($this->stations, $station);
        }

        return true;
    }

    private function InitProgramsFromJson($jsonDataPrograms, &$error) : bool
    {
        unset($this->programs);
        $this->programs = array();

        if (!property_exists($jsonDataPrograms, "pd"))
        {
            $error = "Section [programs/pd] missing from json";
            return false;
        }        

        foreach ($jsonDataPrograms->pd as $key => $programData)
        {
            $program = new SprinklerProgram();

            $program->InitFromProgramData($key, $programData);

            array_push($this->programs, $program);
        }

        return true;
    }

    public function GetStationCount() : int
    {
        return count($this->stations);
    }

    public function GetStation(int $index) : SprinklerStation
    {
        return $this->stations[$index];
    }

    public function GetStationsAsJson()
    {
        $sprinkler = [];

        foreach ($this->stations as $station)
            $sprinkler[] = $station->GetAsJson();

        return $sprinkler;
    }

    // public function UpdateSprinklerOptionInArray(int $sprinklerIndex, bool $enableOption, array &$newJsonDataDisabled) : bool
    // {
    //     $byteIndex = (int)($sprinklerIndex / 8);
    
    //     if ($byteIndex >= count($options))
    //         return false;
    
    //     if ($enableOption)
    //         $options[$byteIndex] = $options[$byteIndex] | pow(2, $sprinklerIndex % 8);
    //     else
    //         $options[$byteIndex] = $options[$byteIndex] & 0xffffffff - pow(2, $sprinklerIndex % 8);
    
    //     return true;
    // }

    private function Log(int $logLevel, string $function, string $message)
    {
        if ($this->logLevel >= $logLevel && isSet($this->logCallback))
            call_user_func($this->logCallback, $logLevel, $function, $message);
    }

    public function Dump()
    {
        print("Device Time: " . date("j.n.Y, H:i:s", $this->DeviceTime) . "\n");
        print("Number of Boards: " . $this->NumberOfBoards . "\n");
        print("Operation Enable: " . ($this->OperationEnable ? "Yes" : "No") . "\n");

        print("Sensor 1: " . ($this->Sensor1Active ? "Active" : "Not active") . "\n");
        print("Sensor 2: " . ($this->Sensor2Active ? "Active" : "Not active") . "\n");
        
        print("Rain delay: " . ($this->RainDelay == 0 ? "Not active" : "Until " . date("j.n.Y, H:i:s", $this->RainDelay)) . "\n");    

        print("Stations:\n");
        foreach ($this->stations as $station)
            $station->Dump();

        print("Programs:\n");
        foreach ($this->programs as $program)
            $program->Dump();
    }
}
