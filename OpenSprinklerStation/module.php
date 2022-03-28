<?php

declare(strict_types=1);

require_once __DIR__ . "/../libs/BaseIPSModule.php";
require_once __DIR__ . "/../libs/SprinklerStation.php";
require_once __DIR__ . "/../OpenSprinklerController/module.php";

class OpenSprinklerStation extends BaseIPSModule
{
    const MODULE_GUID = "{DE0EA757-F6F3-4CC7-3FD0-39622E94EB35}";
    const MODULE_GUID_RX = "{E04B8FB3-7E04-8240-B071-A4ACDF2D3BA1}";
    const MODULE_GUID_TX = "{E23D04A3-4D6D-F886-36E9-4AD77509705F}";

    const PROPERTY_Controller = "Controller";
    const PROPERTY_Index = "Index";

    const VARIABLE_Status = "Status";
    const VARIABLE_WeatherAdjusted = "WeatherAdjusted";
    const VARIABLE_Sensor1Enabled = "Sensor1Enabled";
    const VARIABLE_Sensor2Enabled = "Sensor2Enabled";
    const VARIABLE_Serialized = "Serialized";

    const CMD_StationStatus = "StationStatus";

    public function Create()
    {
        // Diese Zeile nicht löschen
        parent::Create();

        $this->RegisterProperties();
        $this->RegisterVariableProfiles();
        $this->RegisterVariables();

        // Connect to Controller
        $this->ConnectParent(OpenSprinklerController::MODULE_GUID);
    }

    public function ApplyChanges()
    {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();

        // $receiveDataFilter = ".*\"Destination\":\"" . OpenSprinklerStation::class . "\".*";
        // $this->SendDebug(__FUNCTION__, "ReceiveDataFilter=" . $receiveDataFilter, 0);
        // $this->SetReceiveDataFilter($receiveDataFilter);
    }

    private function RegisterProperties()
    {
        $this->RegisterPropertyInteger(self::PROPERTY_Controller, -1);
        $this->RegisterPropertyInteger(self::PROPERTY_Index, -1);
    }

    private function RegisterVariableProfiles()
    {
        if (!IPS_VariableProfileExists("OpenSprinkler.StationStatus"))
        {
            IPS_CreateVariableProfile("OpenSprinkler.StationStatus", 1);

            IPS_SetVariableProfileAssociation("OpenSprinkler.StationStatus", SprinklerStation::STATUS_Unknown, $this->Translate("Unknown"), "", -1);
            IPS_SetVariableProfileAssociation("OpenSprinkler.StationStatus", SprinklerStation::STATUS_Deactivated, $this->Translate("Deactivated"), "", -1);
            IPS_SetVariableProfileAssociation("OpenSprinkler.StationStatus", SprinklerStation::STATUS_Idle, $this->Translate("Idle"), "", -1);
            IPS_SetVariableProfileAssociation("OpenSprinkler.StationStatus", SprinklerStation::STATUS_Scheduled, $this->Translate("Scheduled"), "", -1);
            IPS_SetVariableProfileAssociation("OpenSprinkler.StationStatus", SprinklerStation::STATUS_Running, $this->Translate("Running"), "", -1);
        }
    }

    private function RegisterVariables()
    {
        $this->RegisterVariableInteger(self::VARIABLE_Status, $this->Translate("Status"), "OpenSprinkler.StationStatus", 1);

        $this->RegisterVariableBoolean(self::VARIABLE_WeatherAdjusted, $this->Translate("WeatherAdjusted"), "~Switch", 10);
        $this->RegisterVariableBoolean(self::VARIABLE_Sensor1Enabled, $this->Translate("Sensor 1 enabled"), "~Switch", 13);
        $this->RegisterVariableBoolean(self::VARIABLE_Sensor2Enabled, $this->Translate("Sensor 2 enabled"), "~Switch", 13);
        $this->RegisterVariableBoolean(self::VARIABLE_Serialized, $this->Translate("Serialized"), "~Switch", 13);

        $this->EnableAction(self::VARIABLE_WeatherAdjusted);
        $this->EnableAction(self::VARIABLE_Serialized);
    }

    public function GetConfigurationForm()
    {
        $elements = [];

        $elements[] = [
            "type" => "ExpansionPanel",
            "caption" => "Station Configuration",
            "items" =>
            [
                [
                    "type" => "Label",
                    "name" => "Controller",
                    "caption" => sprintf($this->Translate("Controller Id: %s"), $this->ReadPropertyInteger(self::PROPERTY_Controller))
                ],
                [
                    "type" => "Label",
                    "name" => "StationIndex",
                    "caption" => sprintf($this->Translate("Station Index: %s"), $this->ReadPropertyInteger(self::PROPERTY_Index))
                ]
            ]
        ];

        $actions = [];

        $actions[] = [
            "type" => "Button",
            "label" => "Enable",
            "onClick" => "OpenSprinkler_Enable(\$id, true);"
        ];

        $actions[] = [
            "type" => "Button",
            "label" => "Disable",
            "onClick" => "OpenSprinkler_Enable(\$id, false);"
        ];

        $actions[] = [
            "type" => "Button",
            "label" => "Start (30s)",
            "onClick" => "OpenSprinkler_Run(\$id, true, 30);"
        ];

        $actions[] = [
            "type" => "Button",
            "label" => "Stop",
            "onClick" => "OpenSprinkler_Run(\$id, false, 0);"
        ];

        $form = [];
        $form["elements"] = $elements;
        $form["actions"] = $actions;

        $this->SendDebug(__FUNCTION__, "form=" . print_r($form, true), 0);

        return json_encode($form);
    }

    public function ReceiveData($jsonString)
    {
        $this->SendDebug(__FUNCTION__, "data=" . $jsonString, 0);

        // Empfangene Daten vom Controller Modul
        $jsonMsg = json_decode($jsonString);

        if (property_exists($jsonMsg, OpenSprinklerController::MSGARG_Destination)
            && $jsonMsg->{OpenSprinklerController::MSGARG_Destination} == self::class
            && property_exists($jsonMsg, OpenSprinklerController::MSGARG_Command)
            && property_exists($jsonMsg, OpenSprinklerController::MSGARG_Data))
        {
            $this->ProcessMsg($jsonMsg);
        }
    }

    private function ProcessMsg($msg)
    {
        $this->SendDebug(__FUNCTION__, "msg=" . print_r($msg, true), 0);

        $command = $msg->{OpenSprinklerController::MSGARG_Command};

        switch ($command)
        {
            case self::CMD_StationStatus:
                $stations = $msg->{OpenSprinklerController::MSGARG_Data};

                $stationIndex = $this->ReadPropertyInteger(self::PROPERTY_Index);

                foreach ($stations as $station)
                {
                    if ($station->Index == $stationIndex)
                        $this->UpdateVariables($station);
                }
                break;
        }
    }

    public function RequestAction($ident, $value)
    {
        $this->SendDebug(__FUNCTION__, "ident=" . $ident . ", value=" . $value, 0);

        switch ($ident)
        {
            case self::VARIABLE_WeatherAdjusted:
                // TBD
                break;

            case self::VARIABLE_Serialized:
                // TBD
                break;
        }
    }

    private function UpdateVariables($stationData)
    {
        $this->SendDebug(__FUNCTION__, "index=$stationData->Index, data=" . print_r($stationData, true), 0);

        SetValueInteger($this->GetIDForIdent(self::VARIABLE_Status), SprinklerStation::TranslateToStatus($stationData->Enabled, $stationData->Active, $stationData->ScheduledTime));

        SetValueBoolean($this->GetIDForIdent(self::VARIABLE_WeatherAdjusted), $stationData->WeatherAdjusted);

        if ($stationData->Sensor1Enabled == null)
        {
            $this->UnregisterVariable(self::VARIABLE_Sensor1Enabled);
        }
        else
        {
            $this->RegisterVariableBoolean(self::VARIABLE_Sensor1Enabled, $this->Translate("Sensor 1 Enabled"), "~Switch", 11);
            SetValueBoolean($this->GetIDForIdent(self::VARIABLE_Sensor1Enabled), $stationData->Sensor1Enabled);
        }

        if ($stationData->Sensor2Enabled == null)
        {
            $this->UnregisterVariable(self::VARIABLE_Sensor2Enabled);
        }
        else
        {
            $this->RegisterVariableBoolean(self::VARIABLE_Sensor2Enabled, $this->Translate("Sensor 2 Enabled"), "~Switch", 11);
            SetValueBoolean($this->GetIDForIdent(self::VARIABLE_Sensor2Enabled), $stationData->Sensor2Enabled);
        }

        SetValueBoolean($this->GetIDForIdent(self::VARIABLE_Serialized), $stationData->Serialized);
    }

    public function EnableStation(bool $enable)
    {
        $sendData = [
            'DataID' => self::MODULE_GUID_TX,
            'Command' => OpenSprinklerController::CMD_EnableStation,
            OpenSprinklerController::CMDPARAM_Index => $this->ReadPropertyInteger(self::PROPERTY_Index),
            OpenSprinklerController::CMDPARAM_Enable => $enable
        ];

        $this->SendDataToParent(json_encode($sendData));
    }

    public function SwitchStation(bool $enable, int $duration)
    {
        $sendData = [
            'DataID' => self::MODULE_GUID_TX,
            'Command' => OpenSprinklerController::CMD_SwitchStation,
            OpenSprinklerController::CMDPARAM_Index => $this->ReadPropertyInteger(self::PROPERTY_Index),
            OpenSprinklerController::CMDPARAM_Enable => $enable,
            OpenSprinklerController::CMDPARAM_Duration => $duration
        ];

        $this->SendDataToParent(json_encode($sendData));
    }
}

?>
