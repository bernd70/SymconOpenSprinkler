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
    const VARIABLE_NumberOfBoards = "NumberOfBoards";
    const VARIABLE_RainDelay = "RainDelay";
    const VARIABLE_Sensor1Active = "Sensor1Active";
    const VARIABLE_Sensor2Active = "Sensor2Active";

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
        if (!IPS_VariableProfileExists("OpenSprinkler.Station"))
            IPS_CreateVariableProfile("OpenSprinkler.Station", 1);

        if (!IPS_VariableProfileExists("OpenSprinkler.Program"))
            IPS_CreateVariableProfile("OpenSprinkler.Program", 1);
    }

    private function RegisterVariables()
    {
        $this->RegisterVariableBoolean(self::VARIABLE_Enabled, $this->Translate("Enabled"), "~Switch", 1);
        $this->RegisterVariableInteger(self::VARIABLE_NumberOfBoards, $this->Translate("Number of Boards"), "", 2);
        $this->RegisterVariableBoolean(self::VARIABLE_Sensor1Active, $this->Translate("Sensor 1 active"), "", 3);
        $this->RegisterVariableBoolean(self::VARIABLE_Sensor2Active, $this->Translate("Sensor 2 active"), "", 4);
        $this->RegisterVariableString(self::VARIABLE_RainDelay, $this->Translate("Rain Delay"), "", 5);
    }

    public function GetConfigurationForm()
    {
        $elements = [];

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

            foreach ($stations as $station)
            {
                $instanceId = $this->GetStationInstance("", $station->Index);

                $addValue = [
                    "Name"        => $station->Name,
                    "Index"       => $station->Index,
                    "instanceID"  => $instanceId,
                    "create"      => [
                        "moduleID" => "{DE0EA757-F6F3-4CC7-3FD0-39622E94EB35}",
                        "location" => $importCategoryLocation,
                        "name" => $station->Name,
                        "configuration" => [
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
                [ "name" => "Name", "caption" => "Name", "width" => "auto", "visible" => true ]
            ],
            "values"    => $formStations
        ];

        $form = [];
        $form["elements"] = $elements;

        $this->SendDebug(__FUNCTION__, "form=" . print_r($form, true), 0);

        return json_encode($form);
    }

    public function ReceiveData($jsonString)
    {
        // $this->SendDebug(__FUNCTION__, "data=" . $jsonString, 0);

        // Empfangene Daten vom IO Modul
        $jsonData = json_decode($jsonString);
        $jsonMsg = json_decode($jsonData->Buffer, true);

        if (array_key_exists(OpenSprinklerIO::MSGARG_Destination, $jsonMsg)
            && $jsonMsg[OpenSprinklerIO::MSGARG_Destination] == self::class
            && array_key_exists(OpenSprinklerIO::MSGARG_Command, $jsonMsg)
            && array_key_exists(OpenSprinklerIO::MSGARG_Data, $jsonMsg))
        {
            $this->ProcessMsg($jsonMsg);
        }
    }

    private function ProcessMsg($msg)
    {
        $this->SendDebug(__FUNCTION__, "data=" . print_r($msg, true), 0);

        $command = $msg[OpenSprinklerIO::MSGARG_Command];


        switch ($command)
        {
            case OpenSprinklerIO::CMD_ControllerConfig:
                $sprinklerControllerConfig = new SprinklerControllerConfig();

                $sprinklerControllerConfig->SetFromJson($msg[OpenSprinklerIO::MSGARG_Data]);

                SetValueBoolean($this->GetIDForIdent(self::VARIABLE_Enabled), $sprinklerControllerConfig->OperationEnable);
                SetValueInteger($this->GetIDForIdent(self::VARIABLE_NumberOfBoards), $sprinklerControllerConfig->NumberOfBoards);
                SetValueBoolean($this->GetIDForIdent(self::VARIABLE_Sensor1Active), $sprinklerControllerConfig->Sensor1Active);
                SetValueBoolean($this->GetIDForIdent(self::VARIABLE_Sensor2Active), $sprinklerControllerConfig->Sensor2Active);

                if ($sprinklerControllerConfig->RainDelay == 0)
                    SetValueString($this->GetIDForIdent(self::VARIABLE_RainDelay), $this->Translate("Not active"));
                else
                    SetValueString($this->GetIDForIdent(self::VARIABLE_RainDelay), sprintf($this->Translate("Until %s"), $sprinklerControllerConfig->GetLocalRainDelayTimeAsString()));
        }
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
}

?>
