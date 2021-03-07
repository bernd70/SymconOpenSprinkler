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
    const CMDPARAM_Enable = "Enable";
    const CMDPARAM_Duration = "Duration"; // seconds
    
    // Commands
    const CMD_GetStations = "GetStations";
    
    const CMD_EnableStation = "EnableStation";
    const CMD_RunStation = "RunStation";

    public function Create()
    {
        // Diese Zeile nicht löschen
        parent::Create();

        $this->RegisterPropertyString(self::PROPERTY_Host, "");
        $this->RegisterPropertyString(self::PROPERTY_Password, "");
        $this->RegisterPropertyInteger(self::PROPERTY_UpdateInterval, 10);

        $this->RegisterTimer('Update', 0, 'OpenSprinkler_UpdateStatus($_IPS[\'TARGET\'], 0);');        
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

        // Send data to Sprinkler Stations
        $this->SendData(json_encode($sprinklerController->GetStationsAsJson()));

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

    protected function SendData($buf)
    {
        $data = ['DataID' => '{6B94F6BD-A4F6-87B3-A9D1-907A1D7C30EF}', 'Buffer' => $buf];
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $this->SendDataToChildren(json_encode($data));
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
                case self::CMD_GetStations:
                    $result = $this->GetStations($ret);
                    break;

                case self::CMD_EnableStation:
                    $result = $this->EnableStation($jdata[self::CMDPARAM_Index], $jdata[self::CMDPARAM_Enable]);
                    break;

                case self::CMD_RunStation:
                    if (!$this->CheckProperties($jdata, $error, self::CMDPARAM_Index, self::CMDPARAM_Enable, self::CMDPARAM_Duration))
                        return false;
                    $result = $this->RunStation($jdata[self::CMDPARAM_Index], $jdata[self::CMDPARAM_Enable], $jdata[self::CMDPARAM_Duration]);
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

    private function CheckProperties($jsonData, string &$error, ...$propertyNames) : bool
    {
        foreach($propertyNames as $propertyName)
        {
            if (!property_exists($jsonData, $propertyName))
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

    private function GetStations(&$stations) : bool
    {
        $sprinklerController = $this->InitController();
        if ($sprinklerController == false)
            return false;

        $stations = $sprinklerController->stations;

        return true;        
    }

    private function EnableStation(int $stationIndex, bool $enable) : bool
    {
        $sprinklerController = $this->InitController();
        if ($sprinklerController == false)
            return false;

        return $sprinklerController->EnableStation($stationIndex, $enable, $error);
    }

    private function RunStation(int $stationIndex, bool $enable, int $duration) : bool
    {
        $sprinklerController = $this->InitController();
        if ($sprinklerController == false)
            return false;

        return $sprinklerController->RunStation($stationIndex, $enable, $duration, $error);
    }    
}

?>
