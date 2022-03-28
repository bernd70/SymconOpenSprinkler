<?php

declare(strict_types=1);

require_once __DIR__ . "/../libs/BaseIPSModule.php";
require_once __DIR__ . "/../libs/SprinklerController.php";
require_once __DIR__ . "/../libs/SprinklerStation.php";
require_once __DIR__ . "/../OpenSprinklerStation/module.php";

class OpenSprinklerController extends BaseIPSModule
{
    const MODULE_GUID = "{9A5927FD-F8FD-71DC-5476-6108A289441F}";
    // const MODULE_GUID_RX = "{FD530036-4319-5FCF-261B-573E345DD4FE}";
    // const MODULE_GUID_TX = "{B563DB92-6ACD-0FF6-1CE6-806DDF26FA17}";

    const PROPERTY_Host = "Host";
    const PROPERTY_Password = "Password";
    const PROPERTY_UpdateInterval = "UpdateInterval";
    const PROPERTY_ImportCategory = "ImportCategory";

    const VARIABLE_Enabled = "Enabled";
    const VARIABLE_WeatherMethod = "WeatherMethod";
    const VARIABLE_Waterlevel = "Waterlevel";
    const VARIABLE_RainDelay = "RainDelay";
    const VARIABLE_Sensor1 = "Sensor1";
    const VARIABLE_Sensor2 = "Sensor2";

   // Commands
    const CMD_EnableStation = "EnableStation";
    const CMD_SwitchStation = "SwitchStation";

    // Common parameters
    const CMDPARAM_Index = "Index";
    const CMDPARAM_Name = "Name";
    const CMDPARAM_Enable = "Enable";
    const CMDPARAM_Duration = "Duration"; // seconds
    const CMDPARAM_UseWeather = "UseWeather";

    // SendData Args
    const MSGARG_Destination = "Destination";
    const MSGARG_Command = "Command";
    const MSGARG_Data = "Data";

    public function Create()
    {
        // Diese Zeile nicht löschen
        parent::Create();

        $this->RegisterProperties();
        $this->RegisterVariableProfiles();
        $this->RegisterVariables();

        $this->RegisterTimer('Update', 0, 'OpenSprinkler_OnTimerUpdateStatus($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges()
    {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();

        $receiveDataFilter = ".*\"Destination\":\"" . OpenSprinklerController::class . "\".*";
        $this->SendDebug(__FUNCTION__, "ReceiveDataFilter=$receiveDataFilter", 0);
        $this->SetReceiveDataFilter($receiveDataFilter);

        $refs = $this->GetReferenceList();
        foreach ($refs as $ref)
        {
            $this->UnregisterReference($ref);
        }
        $propertyNames = [self::PROPERTY_ImportCategory];

        foreach ($propertyNames as $name) {
            $oid = $this->ReadPropertyInteger($name);
            if ($oid > 0) {
                $this->RegisterReference($oid);
            }
        }

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

        $this->SetStatus(IS_ACTIVE);
    }

    private function RegisterProperties()
    {
        $this->RegisterPropertyString(self::PROPERTY_Host, "");
        $this->RegisterPropertyString(self::PROPERTY_Password, "");
        $this->RegisterPropertyInteger(self::PROPERTY_UpdateInterval, 10);

        $this->RegisterPropertyInteger(self::PROPERTY_ImportCategory, 0);
    }

    private function RegisterVariableProfiles()
    {
        if (!IPS_VariableProfileExists("OpenSprinkler.SensorType"))
        {
            IPS_CreateVariableProfile("OpenSprinkler.SensorType", 1);

            IPS_SetVariableProfileAssociation("OpenSprinkler.SensorType", SprinklerControllerConfig::SENSORTYPE_Inactive, $this->Translate("Inactive"), "", -1);
            IPS_SetVariableProfileAssociation("OpenSprinkler.SensorType", SprinklerControllerConfig::SENSORTYPE_Rain, $this->Translate("Rain"), "", -1);
            IPS_SetVariableProfileAssociation("OpenSprinkler.SensorType", SprinklerControllerConfig::SENSORTYPE_Flow, $this->Translate("Flow"), "", -1);
            IPS_SetVariableProfileAssociation("OpenSprinkler.SensorType", SprinklerControllerConfig::SENSORTYPE_Soil, $this->Translate("Soil"), "", -1);
            IPS_SetVariableProfileAssociation("OpenSprinkler.SensorType", SprinklerControllerConfig::SENSORTYPE_Program, $this->Translate("Program"), "", -1);
        }

        if (!IPS_VariableProfileExists("OpenSprinkler.WeatherMethod"))
        {
            IPS_CreateVariableProfile("OpenSprinkler.WeatherMethod", 1);

            IPS_SetVariableProfileAssociation("OpenSprinkler.WeatherMethod", SprinklerControllerConfig::WEATHERMETHOD_Manual, $this->Translate("Manual control"), "", -1);
            IPS_SetVariableProfileAssociation("OpenSprinkler.WeatherMethod", SprinklerControllerConfig::WEATHERMETHOD_Zimmerman, $this->Translate("Zimmerman"), "", -1);
            IPS_SetVariableProfileAssociation("OpenSprinkler.WeatherMethod", SprinklerControllerConfig::WEATHERMETHOD_AutoDelayOnRain, $this->Translate("Auto Delay on Rain"), "", -1);
            IPS_SetVariableProfileAssociation("OpenSprinkler.WeatherMethod", SprinklerControllerConfig::WEATHERMETHOD_Evapotranspiration, $this->Translate("Evapotranspiration"), "", -1);
        }

        // if (!IPS_VariableProfileExists("OpenSprinkler.Station"))
        //     IPS_CreateVariableProfile("OpenSprinkler.Station", 1);

        // if (!IPS_VariableProfileExists("OpenSprinkler.Program"))
        //     IPS_CreateVariableProfile("OpenSprinkler.Program", 1);
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

    private function RegisterVariables()
    {
        $this->RegisterVariableBoolean(self::VARIABLE_Enabled, $this->Translate("Enabled"), "~Switch", 1);
        $this->RegisterVariableInteger(self::VARIABLE_Sensor1, $this->Translate("Sensor 1"), "OpenSprinkler.SensorType", 3);
        $this->RegisterVariableInteger(self::VARIABLE_Sensor2, $this->Translate("Sensor 2"), "OpenSprinkler.SensorType", 4);
        $this->RegisterVariableInteger(self::VARIABLE_WeatherMethod, $this->Translate("Weather method"), "OpenSprinkler.WeatherMethod", 5);
        $this->RegisterVariableInteger(self::VARIABLE_Waterlevel, $this->Translate("Waterlevel"), "~Humidity", 6);
        $this->RegisterVariableString(self::VARIABLE_RainDelay, $this->Translate("Rain Delay"), "", 7);
    }

    public function GetConfigurationForm()
    {
        $elements = [];

        $elements[] = [
            "name" => "Host",
            "type" => "ValidationTextBox",
            "caption" => $this->Translate("Hostname or IP address")
        ];

        $elements[] = [
            "name" => "Password",
            "type" => "PasswordTextBox",
            "caption" => $this->Translate("Password")
        ];

        $elements[] = [
            "name" => "UpdateInterval",
            "type" => "NumberSpinner",
            "caption" => $this->Translate("Polling interval"),
            "suffix" => $this->Translate("seconds")
        ];

        if ($this->GetStatus() == IS_ACTIVE)
        {
            if ($this->GetControllerConfig($config) == false)
            {
                $this->SetStatus(201);
            }
            else if ($this->IsConfigured())
            {
                $elements[] = [
                    "type" => "ExpansionPanel",
                    "caption" => "Controller Configuration",
                    "items" =>
                    [
                        [
                            "type" => "Label",
                            "name" => "DeviceTime",
                            "caption" => $this->Translate("Device time") . ": " . $config->GetLocalDeviceTimeAsString()
                        ],
                        [
                            "type" => "Label",
                            "name" => "NumberOfBoards",
                            "caption" => $this->Translate("Number of boards") . ": $config->NumberOfBoards"
                        ]
                    ]
                ];

                $elements[] = [
                    "name"  =>  "ImportCategory",
                    "type" => "SelectCategory",
                    "caption" => $this->Translate("Category for new Sprinkler stations")
                ];

                $formStations = [];

                if ($this->GetStations($stations) !== false)
                {
                    $importCategoryLocation = $this->GetImportCategoryLocation();
                    $stationInstanceIds = IPS_GetInstanceListByModuleID(OpenSprinklerStation::MODULE_GUID);

                    foreach ($stations as $station)
                    {
                        $stationInstanceId = $this->FindStationInstance($stationInstanceIds, $this->InstanceID, $station->Index);

                        $addValue = [
                            "Name"        => $station->Name,
                            "Index"       => $station->Index,
                            "Enabled"     => $this->Translate($station->Enabled ? "Yes" : "No"),
                            "instanceID"  => $stationInstanceId,
                            "create"      => [
                                "moduleID" => OpenSprinklerStation::MODULE_GUID,
                                "location" => $importCategoryLocation,
                                "name" => $station->Name,
                                "configuration" => [
                                    OpenSprinklerStation::PROPERTY_Controller => $this->InstanceID,
                                    OpenSprinklerStation::PROPERTY_Index => $station->Index,
                                    "variables" => [
                                        OpenSprinklerStation::VARIABLE_Status => $station->GetStatus(),
                                        OpenSprinklerStation::VARIABLE_WeatherAdjusted => $station->WeatherAdjusted,
                                        OpenSprinklerStation::VARIABLE_Sensor1Enabled => $station->Sensor1Enabled,
                                        OpenSprinklerStation::VARIABLE_Sensor2Enabled => $station->Sensor2Enabled,
                                        OpenSprinklerStation::VARIABLE_Serialized => $station->Serialized
                                    ]
                                ]
                            ]
                        ];

                        $formStations[] = $addValue;
                    }

                    $elements[] = [
                        "name"      => OpenSprinklerController::class,
                        "type"      => "Configurator",
                        "rowCount"  => count($stations) ,
                        "add"       => false,
                        "delete"    => false,
                        "sort"      => [ "column" => "Index", "direction" => "ascending" ],
                        "columns"   => [
                            [ "name" => "Index", "caption" => "Index", "width" => "100px", "visible" => true ],
                            [ "name" => "Enabled", "caption" => "Enabled", "width" => "70px", "visible" => true ],
                            [ "name" => "Name", "caption" => "Name", "width" => "auto", "visible" => true ]
                        ],
                        "values"    => $formStations
                    ];
                }
            }
        }

        $form = [];
        $form["elements"] = $elements;

        $status = [];

        $status[] = [
            "code" => 201,
            "icon" => "error",
            "caption" => $this->Translate("OpenSprinkler communications error (Check Host and password, Detail in Log)")
        ];

        $form["status"] = $status;

        // $this->SendDebug(__FUNCTION__, "form=" . print_r($form, true), 0);

        return json_encode($form);
    }

    private function InitController()
    {
        $sprinklerController = new SprinklerController();

        $sprinklerController->Init($this->ReadPropertyString(self::PROPERTY_Host), $this->ReadPropertyString(self::PROPERTY_Password));
        $sprinklerController->SetLogCallback(Closure::fromCallable([$this, "LogCallback"]), SprinklerController::LOGLEVEL_DEBUG);

        if (!$sprinklerController->Read($error))
        {
            $this->LogMessage("Read Error: " . $error, KL_ERROR);
            return false;
        }

        return $sprinklerController;
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

    private function IsConfigured() : bool
    {
        $host = $this->ReadPropertyString(self::PROPERTY_Host);
        $password  = $this->ReadPropertyString(self::PROPERTY_Password);

        return ($host !== false && $host != "" && $password !== false && $password != "");
    }

    public function OnTimerUpdateStatus()
    {
        $this->InternalUpdateStatus(false);
    }

    public function UpdateStatus() : bool
    {
        return $this->InternalUpdateStatus(true);
    }

    private function InternalUpdateStatus(bool $forceUpdate) : bool
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
        $this->SendDebug(__FUNCTION__, 'configMD5 old=' . $this->GetBuffer("Config") . ", new =$configMD5", 0);

        if ($forceUpdate || $this->GetBuffer("Config") !== $configMD5)
        {
            $this->SendDebug(__FUNCTION__, "Configuration changed", 0);

            SetValueBoolean($this->GetIDForIdent(self::VARIABLE_Enabled), $config->OperationEnable);
            SetValueInteger($this->GetIDForIdent(self::VARIABLE_Sensor1), $config->Sensor1Type);
            SetValueInteger($this->GetIDForIdent(self::VARIABLE_Sensor2), $config->Sensor2Type);
            SetValueInteger($this->GetIDForIdent(self::VARIABLE_WeatherMethod), $config->WeatherMethod);
            SetValueInteger($this->GetIDForIdent(self::VARIABLE_Waterlevel), $config->WaterLevel);

            if ($config->RainDelay == 0)
                SetValueString($this->GetIDForIdent(self::VARIABLE_RainDelay), $this->Translate("Not active"));
            else
                SetValueString($this->GetIDForIdent(self::VARIABLE_RainDelay), sprintf($this->Translate("Until %s"), $config->GetLocalRainDelayTimeAsString()));

            $this->SetBuffer("Config", $configMD5);
        }

        $stations = $sprinklerController->GetStations();
        $stationStatusMD5 = MD5(json_encode($stations));
        $this->SendDebug(__FUNCTION__, 'stationStatusMD5 old=' . $this->GetBuffer("StationStatus") . ", new =$stationStatusMD5", 0);

        if ($forceUpdate || $this->GetBuffer("StationStatus") !== $stationStatusMD5)
        {
            $this->SendDebug(__FUNCTION__, "Station status changed", 0);

            $this->SendData(OpenSprinklerStation::class, OpenSprinklerStation::CMD_StationStatus, $stations);

            $this->SetBuffer("StationStatus", $stationStatusMD5);
        }

        return true;
    }

    protected function SendData(string $destination, string $command, $data)
    {
        $sendData = [
            'DataID' => OpenSprinklerStation::MODULE_GUID_RX,
            self::MSGARG_Destination => $destination,
            self::MSGARG_Command => $command,
            self::MSGARG_Data => $data
        ];

        $this->SendDebug(__FUNCTION__, 'data=' . print_r($sendData, true), 0);

        $this->SendDataToChildren(json_encode($sendData));
    }

    public function ReceiveData($jsonString)
    {
        // $this->SendDebug(__FUNCTION__, "data=" . $jsonString, 0);

        // Empfangene Daten vom IO Modul
        $jsonMsg = json_decode($jsonString);

        if (property_exists($jsonMsg, self::MSGARG_Destination)
            && $jsonMsg->{self::MSGARG_Destination} == self::class
            && property_exists($jsonMsg, self::MSGARG_Command)
            && property_exists($jsonMsg, self::MSGARG_Data))
        {
            $this->ProcessMsg($jsonMsg);
        }
    }

    private function ProcessMsg($msg)
    {
        $this->SendDebug(__FUNCTION__, "data=" . print_r($msg, true), 0);

        $command = $msg->{self::MSGARG_Command};

        switch ($command)
        {

        }
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
                case self::CMD_EnableStation:
                    $result = $this->EnableStation($jdata[self::CMDPARAM_Index], $jdata[self::CMDPARAM_Enable]);
                    break;

                case self::CMD_SwitchStation:
                    if (!$this->CheckProperties($jdata, $error, self::CMDPARAM_Index, self::CMDPARAM_Enable, self::CMDPARAM_Duration))
                        return false;
                    $result = $this->SwitchStation($jdata[self::CMDPARAM_Index], $jdata[self::CMDPARAM_Enable], $jdata[self::CMDPARAM_Duration]);
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

    private function FindStationInstance($stationInstanceIds, $controllerId, $stationIndex)
    {
        if ($stationInstanceIds == null)
            $stationInstanceIds = IPS_GetInstanceListByModuleID(OpenSprinklerStation::MODULE_GUID);

        foreach ($stationInstanceIds as $stationInstanceId)
        {
            if (IPS_GetProperty($stationInstanceId, OpenSprinklerStation::PROPERTY_Controller) == $controllerId
                && IPS_GetProperty($stationInstanceId, OpenSprinklerStation::PROPERTY_Index) == $stationIndex)
                return $stationInstanceId;
        }

        return 0;
    }

    private function GetImportCategoryLocation()
    {
        $tree_position = [];

        $categoryId = $this->ReadPropertyInteger(self::PROPERTY_ImportCategory);

        if ($categoryId > 0 && IPS_ObjectExists($categoryId))
        {
            $tree_position[] = IPS_GetName($categoryId);
            $parent = IPS_GetObject($categoryId)['ParentID'];

            while ($parent > 0)
            {
                if ($parent > 0)
                    $tree_position[] = IPS_GetName($parent);

                $parent = IPS_GetObject($parent)['ParentID'];
            }

            $tree_position = array_reverse($tree_position);
        }

        return $tree_position;
    }

    public function GetStationIndex(string $name) : int
    {
        $this->GetStations($stations);

        foreach ($stations as $station)
        {
            if (strcasecmp($station->Name, $name) == 0)
                return $station->Index;
        }

        return -1;
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

    private function EnableController(bool $enable) : bool
    {
        $this->LogMessage("EnableController enable=$enable", KL_NOTIFY);

        $sprinklerController = $this->InitController();
        if ($sprinklerController == false)
            return false;

        return $sprinklerController->EnableController($enable, $error);
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
