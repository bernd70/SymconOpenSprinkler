<?php

declare(strict_types=1);

require_once "SprinklerControllerConfig.php";
require_once "SprinklerHelper.php";
require_once "SprinklerStation.php";
require_once "SprinklerProgram.php";

class SprinklerController
{
    private $SprinklerControllerConfig;
    private $stations = [];
    private $programs = [];

    private $host;
    private $passwordHash = "";
    private $retries = 3;

    private $logCallback;
    private $logLevel = 0;

    const LOGLEVEL_STANDARD = 5;
    const LOGLEVEL_DEBUG = 9;

    const LOG_FATAL = 0;
    const LOG_ERROR = 1;
    const LOG_WARNING = 2;
    const LOG_INFO = 3;
    const LOG_DEBUG = 9;

    function __construct()
    {
        $this->SprinklerControllerConfig = new SprinklerControllerConfig();
    }

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

        if (!property_exists($jsonData->settings, "ps"))
        {
            $error = "Section [settings/ps] missing from json";
            return false;
        }

        if (!property_exists($jsonData, "options"))
        {
            $error = "Section [options] missing from json";
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

        if (!$this->InitConfigFromJson($jsonData->settings, $jsonData->options, $error))
            return false;

        if (!$this->InitStationsFromJson($jsonData->stations, $jsonData->status, $jsonData->settings->ps, $error))
            return false;

        if (!$this->InitProgramsFromJson($jsonData->programs, $error))
            return false;

        return true;
    }

    public function EnableController(bool $enable, &$error) : bool
    {
        return $this->ExecuteCommand("cv", "en=" . intval($enable), $jsonData, $error);
    }

    public function EnableStation(int $stationIndex, bool $enable, &$error) : bool
    {
        if (!$this->ExecuteCommand("jn", "", $stationAttributes, $error))
            return false;

        $this->GetBoardAndStationIndex($stationIndex, $boardIndex, $boardStationIndex);

        $stationBit = pow(2, $boardStationIndex);
        $disabledMask = $stationAttributes->stn_dis[$boardIndex];

        // Already in desired state
        if ($disabledMask & $stationBit == !$enable)
            return true;

        if (!$enable)
            $newDisabledMask = $disabledMask | $stationBit;
        else
            $newDisabledMask = $disabledMask - $stationBit;

        return $this->ExecuteCommand("cs", "d$boardIndex=$newDisabledMask", $jsonData, $error);
    }

    public function SwitchStation(int $stationIndex, bool $enable, int $duration, &$error) : bool
    {
        return $this->ExecuteCommand("cm", "sid=$stationIndex&en=" . intval($enable) . "&t=$duration", $jsonData, $error);
    }

    public function RunProgram(string $programName, bool $useWeather, &$error) : bool
    {
        $programIndex = $this->FindProgram($programName);
        if ($programIndex == -1)
        {
            $error = "Program [$programName] not found";
            return false;
        }

        return $this->ExecuteCommand("mp", "pid=$programIndex&uwt=" . intval($useWeather), $jsonData, $error);
    }

    public function StopAllStations(&$error) : bool
    {
        $success = true;

        foreach ($this->stations as $station)
        {
            if ($station->Active || $station->ScheduledTime != 0)
            {
                if (!$this->SwitchStation($station->Index, false, 0, $error))
                {
                    $this->Log(self::LOG_ERROR, __FUNCTION__, "Unable to switch off station $station->Index: $error");
                    $success = false;
                }
            }
        }

        return $success;
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

            $this->Log(self::LOG_DEBUG, __FUNCTION__, "Web Request Error on attempt $attempt: $error");
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

    private function InitConfigFromJson($jsonDataSettings, $jsonDataOptions, &$error) : bool
    {
        GetJsonProperty($jsonDataSettings, "devt", $this->SprinklerControllerConfig->DeviceTime, 0);
        GetJsonProperty($jsonDataSettings, "nbrd", $this->SprinklerControllerConfig->NumberOfBoards, 1);

        GetJsonProperty($jsonDataOptions, "sn1t", $this->SprinklerControllerConfig->Sensor1Type, SprinklerControllerConfig::SENSORTYPE_Inactive);
        GetJsonProperty($jsonDataOptions, "sn2t", $this->SprinklerControllerConfig->Sensor2Type, SprinklerControllerConfig::SENSORTYPE_Inactive);

        GetJsonProperty($jsonDataSettings, "en", $this->SprinklerControllerConfig->OperationEnable, false);
        GetJsonProperty($jsonDataSettings, "rdst", $this->SprinklerControllerConfig->RainDelay, false);

        GetJsonProperty($jsonDataOptions, "wl", $this->SprinklerControllerConfig->WaterLevel, 100);
        GetJsonProperty($jsonDataOptions, "uwt", $this->SprinklerControllerConfig->WeatherMethod, SprinklerControllerConfig::WEATHERMETHOD_Manual);

        return true;
    }

    private function InitStationsFromJson($jsonDataStations, $jsonDataStatus, $jsonDataProgramStatus, &$error) : bool
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
            GetSprinklerOptionFromArray($jsonDataDisabled, $key, $enabled, true);

            $active = $key < count($jsonDataStatus->sn) ? boolval($jsonDataStatus->sn[$key]) : false;

            $station = new SprinklerStation($key, $stationName, $enabled, $active);

            GetSprinklerOptionFromArray($jsonDataIgnoreRain, $key, $ignoreRain);
            GetSprinklerOptionFromArray($jsonDataIgnoreSensor2, $key, $ignoreSensor2);
            GetSprinklerOptionFromArray($jsonDataSerialized, $key, $serialized);

            $sensor1Enabled = null;
            if ($this->SprinklerControllerConfig->Sensor1Type != SprinklerControllerConfig::SENSORTYPE_Inactive)
                GetSprinklerOptionFromArray($jsonDataIgnoreSensor1, $key, $sensor1Enabled, true);

            $sensor2Enabled = null;
            if ($this->SprinklerControllerConfig->Sensor2Type != SprinklerControllerConfig::SENSORTYPE_Inactive)
                GetSprinklerOptionFromArray($jsonDataIgnoreSensor2, $key, $sensor2Enabled, true);

            $station->SetOptions(!$ignoreRain, $sensor1Enabled, $sensor2Enabled, $serialized);

            $schedule = $jsonDataProgramStatus[$key];
            if ($schedule[0] != 0)
            {
                $station->SetScheduled(true, $schedule[1], $schedule[2]);
            }

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
        if ($index >= $this->GetStationCount())
            return null;

        return $this->stations[$index];
    }

    public function GetStations()
    {
        return $this->stations;
    }

    public function GetProgramCount() : int
    {
        return count($this->programs);
    }

    public function GetProgram(int $index) : SprinklerProgram
    {
        if ($index >= $this->GetProgramCount())
            return null;

        return $this->programs[$index];
    }

    public function FindProgram(string $programName) : int
    {
        foreach ($this->programs as $program)
        {
            if (strcasecmp($program->Name, $programName) == 0)
            return $program->Index;
        }

        return -1;
    }

    public function GetConfig(bool $removeStaticData = false)
    {
        if ($removeStaticData)
        {
            $config = new SprinklerControllerConfig($this->SprinklerControllerConfig);

            unset($config->DeviceTime);
            unset($config->NumberOfBoards);

            return $config;
        }
        else
            return $this->SprinklerControllerConfig;
    }

    private function GetBoardAndStationIndex(int $stationIndex, &$boardIndex, &$boardStationIndex)
    {
        $boardIndex = intval($stationIndex / 8);
        $boardStationIndex = $stationIndex % 8;
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
        print("Device Time: " . $this->SprinklerControllerConfig->GetLocalDeviceTimeAsString() . "\n");
        print("Number of Boards: " . $this->SprinklerControllerConfig->NumberOfBoards . "\n");
        print("Operation Enable: " . ($this->SprinklerControllerConfig->OperationEnable ? "Yes" : "No") . "\n");

        if ($this->SprinklerControllerConfig->Sensor1Type != SprinklerControllerConfig::SENSORTYPE_Inactive)
            print("Sensor 1: " . $this->SprinklerControllerConfig->GetSensor1Type() . "\n");

        if ($this->SprinklerControllerConfig->Sensor2Type != SprinklerControllerConfig::SENSORTYPE_Inactive)
            print("Sensor 2: " . $this->SprinklerControllerConfig->GetSensor2Type() . "\n");

        print("Water Level: " . $this->SprinklerControllerConfig->WaterLevel . "%\n");

        print("Weather Method: " . $this->SprinklerControllerConfig->GetWeatherMethod() . "\n");

        print("Rain delay: " . $this->SprinklerControllerConfig->GetLocalRainDelayTimeAsString() . "\n");

        print("Stations:\n");
        foreach ($this->stations as $station)
            $station->Dump();

        print("Programs:\n");
        foreach ($this->programs as $program)
            $program->Dump();
    }
}
