<?php

declare(strict_types=1);

require_once __DIR__ . "/../libs/BaseIPSModule.php";
require_once __DIR__ . "/../libs/SprinklerController.php";

class OpenSprinklerIO extends BaseIPSModule
{
    const MODULE_GUID = "{01F937DF-49BB-47F0-63C7-8119AB4A5C3F}";
    const MODULE_GUID_RX = "{E04B8FB3-7E04-8240-B071-A4ACDF2D3BA1}";

    const PROPERTY_Host = "Host";
    const PROPERTY_Password = "Password";
    const PROPERTY_UpdateInterval = "UpdateInterval";

    // Common parameters
    const CMDPARAM_Index = "Index";
    const CMDPARAM_Name = "Name";
    const CMDPARAM_Enable = "Enable";
    const CMDPARAM_Duration = "Duration"; // seconds
    const CMDPARAM_UseWeather = "UseWeather";

    // Commands
    const CMD_GetControllerConfig = "GetControllerConfig";
    const CMD_GetStations = "GetStations";

    const CMD_EnableStation = "EnableStation";
    const CMD_SwitchStation = "SwitchStation";
    const CMD_StopAllStations = "StopAllStations";
    const CMD_RunProgram = "RunProgram";

    const CMD_StationStatus = "StationStatus";
    const CMD_ControllerConfig = "ControllerConfig";

    // SendDataToParent Args
    const MSGARG_Destination = "Destination";
    const MSGARG_Command = "Command";
    const MSGARG_Data = "Data";

    public function Create()
    {
        // Diese Zeile nicht löschen
        parent::Create();

        $this->RegisterPropertyString(self::PROPERTY_Host, "");
        $this->RegisterPropertyString(self::PROPERTY_Password, "");
        $this->RegisterPropertyInteger(self::PROPERTY_UpdateInterval, 10);

        $this->RegisterTimer('Update', 0, 'OpenSprinkler_UpdateStatus($_IPS[\'TARGET\'], 0);');
    }

    private function UpdateVariableProfiles(SprinklerController $sprinklerController)
    {
        // if (IPS_VariableProfileExists("OpenSprinkler.Station"))
        //     IPS_DeleteVariableProfile("OpenSprinkler.Station");

        // IPS_CreateVariableProfile("OpenSprinkler.Station", 1);

        // for ($stationIndex = 0; $stationIndex < $sprinklerController->GetStationCount(); $stationIndex++)
        // {
        //     $station = $sprinklerController->GetStation($stationIndex);

        //     IPS_SetVariableProfileAssociation("OpenSprinkler.Station", $station->Index, $station->Name, "", -1);
        // }

        // if (IPS_VariableProfileExists("OpenSprinkler.Program"))
        //     IPS_DeleteVariableProfile("OpenSprinkler.Program");

        // IPS_CreateVariableProfile("OpenSprinkler.Program", 1);

        // for ($programIndex = 0; $programIndex < $sprinklerController->GetProgramCount(); $programIndex++)
        // {
        //     $program = $sprinklerController->GetProgram($programIndex);

        //     IPS_SetVariableProfileAssociation("OpenSprinkler.Program", $program->Index, $program->Name, "", -1);
        // }
    }

    public function ApplyChanges()
    {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();

        if ($this->UpdateStatus())
        {
            $this->SetStatus(IS_ACTIVE);

            // Start UpdateStatus Timer
            $this->SetTimerInterval('Update', $this->ReadPropertyInteger(self::PROPERTY_UpdateInterval) * 1000);
        }
        else
        {
            $this->SetStatus(IS_INACTIVE);
        }

        return true;
    }

    public function UpdateStatus() : bool
    {
        if (!$this->IsConfigured())
            return false;

        $sprinklerController = $this->InitController();
        if ($sprinklerController == false)
            return false;

        if (!$sprinklerController->Read($error))
        {
            $this->LogMessage("UpdateStatus Error: " . $error, KL_ERROR);
            return false;
        }

        $this->UpdateVariableProfiles($sprinklerController);

        $config = $sprinklerController->GetConfig(true);
        $configMD5 = MD5(json_encode($config));

        if ($this->GetBuffer("Config") !== $configMD5)
        {
            $this->SendData(OpenSprinklerController::class, OpenSprinklerIO::CMD_ControllerConfig, $config);

            $this->SetBuffer("Config", $configMD5);
        }

        $stations = $sprinklerController->GetStations();
        $stationStatusMD5 = MD5(json_encode($stations));

        if ($this->GetBuffer("StationStatus") !== $stationStatusMD5)
        {
            $this->SendData(OpenSprinklerStation::class, OpenSprinklerIO::CMD_StationStatus,$stations);

            $this->SetBuffer("StationStatus", $stationStatusMD5);
        }

        return true;
    }

    private function InitController() : SprinklerController
    {
        $sprinklerController = new SprinklerController();

        $sprinklerController->Init($this->ReadPropertyString(self::PROPERTY_Host), $this->ReadPropertyString(self::PROPERTY_Password));
        $sprinklerController->SetLogCallback(Closure::fromCallable([$this, "LogCallback"]), SprinklerController::LOGLEVEL_DEBUG);

        if (!$sprinklerController->Read($error))
        {
            $this->LogMessage("UpdateStatus Error: " . $error, KL_ERROR);
            return false;
        }

        return $sprinklerController;
    }

    protected function SendData(string $destination, string $command, $data)
    {
        $sendData = [
            'DataID' => '{6B94F6BD-A4F6-87B3-A9D1-907A1D7C30EF}',
            OpenSprinklerIO::MSGARG_Destination => $destination,
            OpenSprinklerIO::MSGARG_Command => $command,
            OpenSprinklerIO::MSGARG_Data => $data
        ];

        $this->SendDebug(__FUNCTION__, 'data=' . print_r($sendData, true), 0);

        $this->SendDataToChildren(json_encode($sendData));
    }

    public function ForwardData($data)
    {
        if ($this->GetStatus() == IS_INACTIVE)
        {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return false;
        }

        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);

        $ret = '';
        $command = $jdata['Command'];

        if (isset($command))
        {
            switch ($command)
            {
                case self::CMD_GetControllerConfig:
                    $result = $this->GetControllerConfig($ret);
                    break;

                case self::CMD_GetStations:
                    $result = $this->GetStations($ret);
                    break;

                case self::CMD_EnableStation:
                    $result = $this->EnableStation($jdata[self::CMDPARAM_Index], $jdata[self::CMDPARAM_Enable]);
                    break;

                case self::CMD_SwitchStation:
                    if (!$this->CheckProperties($jdata, $error, self::CMDPARAM_Index, self::CMDPARAM_Enable, self::CMDPARAM_Duration))
                        return false;
                    $result = $this->SwitchStation($jdata[self::CMDPARAM_Index], $jdata[self::CMDPARAM_Enable], $jdata[self::CMDPARAM_Duration]);
                    break;

                case self::CMD_RunProgram:
                    if (!$this->CheckProperties($jdata, $error, self::CMDPARAM_Name, self::CMDPARAM_UseWeather))
                        return false;
                    $result = $this->RunProgram($jdata[self::CMDPARAM_Name], $jdata[self::CMDPARAM_UseWeather]);
                    break;

                case self::CMD_StopAllStations:
                    $result = $this->StopAllStations();
                    break;

                default:
                    $this->SendDebug(__FUNCTION__, "Unknown Command: $command", 0);
                    break;
            }
        }
        else
        {
            $this->SendDebug(__FUNCTION__, 'Unknown Message Structure', 0);
        }

        $this->SendDebug(__FUNCTION__, 'ret=' . json_encode($ret), 0);

        return json_encode($ret);
    }


    private function IsConfigured() : bool
    {
        $host = $this->ReadPropertyString(self::PROPERTY_Host);
        $password  = $this->ReadPropertyString(self::PROPERTY_Password);

        return ($host !== false && $host != "" && $password !== false && $password != "");
    }

    private function CheckProperties(array $properties, &$error, ...$propertyNames) : bool
    {
        foreach($propertyNames as $propertyName)
        {
            if (!array_key_exists($propertyName, $properties))
            {
                $error = "Parameter $propertyName missing";
                return false;
            }
        }

        return true;
    }

    protected function LogCallback(int $logLevel, string $function, string $message)
    {
        switch ($logLevel)
        {
            case SprinklerController::LOG_INFO:
                $this->LogMessage($message, KL_NOTIFY);
                break;

            case SprinklerController::LOG_WARNING:
                $this->LogMessage($message, KL_WARNING);
                break;

            case SprinklerController::LOG_ERROR:
            case SprinklerController::LOG_FATAL:
                $this->LogMessage($message, KL_ERROR);
                break;

            case SprinklerController::LOG_DEBUG:
            default:
                $this->SendDebug($function, $message, 0);
                break;
        }
    }

    private function GetControllerConfig(&$config) : bool
    {
        $sprinklerController = $this->InitController();
        if ($sprinklerController == false)
            return false;

        $config = $sprinklerController->GetConfig();

        return true;
    }

    private function GetStations(&$stations) : bool
    {
        $sprinklerController = $this->InitController();
        if ($sprinklerController == false)
            return false;

        $stations = $sprinklerController->GetStations();

        return true;
    }

    private function EnableStation(int $stationIndex, bool $enable) : bool
    {
        $this->LogMessage("EnableStation $stationIndex, enable=$enable", KL_NOTIFY);

        $sprinklerController = $this->InitController();

        if ($sprinklerController == false)
            return false;

        return $sprinklerController->EnableStation($stationIndex, $enable, $error);
    }

    private function SwitchStation(int $stationIndex, bool $enable, int $duration) : bool
    {
        $this->LogMessage("SwitchStation $stationIndex, enable=$enable, duration=$duration", KL_NOTIFY);

        $sprinklerController = $this->InitController();
        if ($sprinklerController == false)
            return false;

        return $sprinklerController->SwitchStation($stationIndex, $enable, $duration, $error);
    }

    private function RunProgram(string $programName, bool $useWeather) : bool
    {
        $this->LogMessage("RunProgram [$programName], useWeather=$useWeather", KL_NOTIFY);

        $sprinklerController = $this->InitController();
        if ($sprinklerController == false)
            return false;

        return $sprinklerController->RunProgram($programName, $useWeather, $error);
    }

    private function StopAllStations() : bool
    {
        $this->LogMessage("StopAllStations", KL_NOTIFY);

        $sprinklerController = $this->InitController();
        if ($sprinklerController == false)
            return false;

        return $sprinklerController->StopAllStations($error);
    }
}

?>
