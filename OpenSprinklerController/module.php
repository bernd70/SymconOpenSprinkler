<?php

declare(strict_types=1);

require_once __DIR__ . "/../libs/BaseIPSModule.php";
require_once __DIR__ . "/../libs/SprinklerController.php";
require_once __DIR__ . "/../libs/SprinklerStation.php";
require_once __DIR__ . "/../OpenSprinklerIO/module.php";
require_once __DIR__ . "/../OpenSprinklerStation/module.php";

class OpenSprinklerController extends BaseIPSModule
{
    const PROPERTY_ImportCategory = "ImportCategory";

    const VARIABLE_Enabled = "Enabled";
    const VARIABLE_WeatherMethod = "WeatherMethod";
    const VARIABLE_Waterlevel = "Waterlevel";
    const VARIABLE_RainDelay = "RainDelay";
    const VARIABLE_Sensor1 = "Sensor1";
    const VARIABLE_Sensor2 = "Sensor2";

    public function Create()
    {
        // Diese Zeile nicht löschen
        parent::Create();

        $this->RegisterProperties();
        $this->RegisterVariableProfiles();
        $this->RegisterVariables();

        // Connect to IO
        $this->ConnectParent(OpenSprinklerIO::MODULE_GUID);
    }

    public function ApplyChanges()
    {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();

        $receiveDataFilter = ".*\"Destination\":\"" . OpenSprinklerController::class . "\".*";
        $this->SendDebug(__FUNCTION__, "ReceiveDataFilter=" . $receiveDataFilter, 0);
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

        $this->SetStatus(IS_ACTIVE);
    }

    private function RegisterProperties()
    {
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

        $config = $this->GetConfig();

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

        $stations = $this->GetStations();
        $formStations = [];

        if (count($stations) > 0)
        {
            $importCategoryLocation = $this->GetImportCategoryLocation();

            foreach ($stations as $stdClassStation)
            {
                $station = new SprinklerStation(0, "", false, false);
                $station->InitFromStdClass($stdClassStation);

                $addValue = [
                    "Name"        => $station->Name,
                    "Index"       => $station->Index,
                    "Enabled"     => $this->Translate($station->Enabled ? "Yes" : "No"),
                    "instanceID"  => $this->GetStationInstance("", $station->Index),
                    "create"      => [
                        "moduleID" => "{DE0EA757-F6F3-4CC7-3FD0-39622E94EB35}",
                        "location" => $importCategoryLocation,
                        "name" => $station->Name,
                        "configuration" => [
                            OpenSprinklerStation::PROPERTY_Controller => $this->InstanceID,
                            OpenSprinklerStation::PROPERTY_Index => $station->Index
                         ]
                    ],
                ];

                $formStations[] = $addValue;
            }
        }

        $elements[] = [
            "name"      => "OpenSprinklerController",
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

        $form = [];
        $form["elements"] = $elements;

        // $this->SendDebug(__FUNCTION__, "form=" . print_r($form, true), 0);

        return json_encode($form);
    }

    public function ReceiveData($jsonString)
    {
        // $this->SendDebug(__FUNCTION__, "data=" . $jsonString, 0);

        // Empfangene Daten vom IO Modul
        $jsonMsg = json_decode($jsonString);

        if (property_exists($jsonMsg, OpenSprinklerIO::MSGARG_Destination)
            && $jsonMsg->{OpenSprinklerIO::MSGARG_Destination} == self::class
            && property_exists($jsonMsg, OpenSprinklerIO::MSGARG_Command)
            && property_exists($jsonMsg, OpenSprinklerIO::MSGARG_Data))
        {
            $this->ProcessMsg($jsonMsg);
        }
    }

    private function ProcessMsg($msg)
    {
        $this->SendDebug(__FUNCTION__, "data=" . print_r($msg, true), 0);

        $command = $msg->{OpenSprinklerIO::MSGARG_Command};

        switch ($command)
        {
            case OpenSprinklerIO::CMD_ControllerConfig:
                $sprinklerControllerConfig = new SprinklerControllerConfig($msg->{OpenSprinklerIO::MSGARG_Data});

                SetValueBoolean($this->GetIDForIdent(self::VARIABLE_Enabled), $sprinklerControllerConfig->OperationEnable);
                SetValueInteger($this->GetIDForIdent(self::VARIABLE_Sensor1), $sprinklerControllerConfig->Sensor1Type);
                SetValueInteger($this->GetIDForIdent(self::VARIABLE_Sensor2), $sprinklerControllerConfig->Sensor2Type);
                SetValueInteger($this->GetIDForIdent(self::VARIABLE_WeatherMethod), $sprinklerControllerConfig->WeatherMethod);
                SetValueInteger($this->GetIDForIdent(self::VARIABLE_Waterlevel), $sprinklerControllerConfig->WaterLevel);

                if ($sprinklerControllerConfig->RainDelay == 0)
                    SetValueString($this->GetIDForIdent(self::VARIABLE_RainDelay), $this->Translate("Not active"));
                else
                    SetValueString($this->GetIDForIdent(self::VARIABLE_RainDelay), sprintf($this->Translate("Until %s"), $sprinklerControllerConfig->GetLocalRainDelayTimeAsString()));

                break;
        }
    }

    private function GetConfig() : SprinklerControllerConfig
    {
        if ($this->HasActiveParent() == false)
        {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);

            return new SprinklerControllerConfig;
        }

        $sendData = ['DataID' => OpenSprinklerIO::MODULE_GUID_RX, 'Command' => OpenSprinklerIO::CMD_GetControllerConfig];
        $jsonConfig = $this->SendDataToParent(json_encode($sendData));

        $this->SendDebug(__FUNCTION__, 'config=' . $jsonConfig, 0);

        return new SprinklerControllerConfig(json_decode($jsonConfig));
    }

    private function GetStations()
    {
        if ($this->HasActiveParent() == false)
        {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);

            return [];
        }

        $sendData = ['DataID' => OpenSprinklerIO::MODULE_GUID_RX, 'Command' => OpenSprinklerIO::CMD_GetStations];
        $jsonStations = $this->SendDataToParent(json_encode($sendData));

        $this->SendDebug(__FUNCTION__, 'stations=' . $jsonStations, 0);

        return json_decode($jsonStations);
    }

    private function GetStationInstance($controllerId, $stationIndex)
    {
        $instanceIds = IPS_GetInstanceListByModuleID("{DE0EA757-F6F3-4CC7-3FD0-39622E94EB35}");

        foreach ($instanceIds as $instanceId)
        {
            // ToDo: Check for correct controller

            if (IPS_GetProperty($instanceId, OpenSprinklerStation::PROPERTY_Index) == $stationIndex)
                return $instanceId;
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

    public function EnableController(bool $enable)
    {
        $sendData = [
            'DataID' => OpenSprinklerIO::MODULE_GUID_RX,
            'Command' => OpenSprinklerIO::CMD_EnableController,
            OpenSprinklerIO::CMDPARAM_Enable => $enable
        ];

        $this->SendDataToParent(json_encode($sendData));
    }

    public function GetStationIndex(string $name) : int
    {
        $stations = $this->GetStations();

        foreach ($stations as $station)
        {
            if (strcasecmp($station->Name, $name) == 0)
                return $station->Index;
        }

        return -1;
    }

    public function StopAllStations()
    {
        $sendData = [
            'DataID' => OpenSprinklerIO::MODULE_GUID_RX,
            'Command' => OpenSprinklerIO::CMD_StopAllStations
        ];

        $this->SendDataToParent(json_encode($sendData));
    }

    public function RunProgram(string $programName, bool $useWeather = true)
    {
        $sendData = [
            'DataID' => OpenSprinklerIO::MODULE_GUID_RX,
            'Command' => OpenSprinklerIO::CMD_RunProgram,
            OpenSprinklerIO::CMDPARAM_Name => $programName,
            OpenSprinklerIO::CMDPARAM_UseWeather => $useWeather
        ];

        $this->SendDataToParent(json_encode($sendData));
    }

    public function SetRainDelay(int $hours)
    {
        $sendData = [
            'DataID' => OpenSprinklerIO::MODULE_GUID_RX,
            'Command' => OpenSprinklerIO::CMD_SetRainDelay,
            OpenSprinklerIO::CMDPARAM_Hours => $hours
        ];

        $this->SendDataToParent(json_encode($sendData));
    }
}

?>
