<?php

declare(strict_types=1);

require_once __DIR__ . "/../libs/BaseIPSModule.php";
require_once __DIR__ . "/../libs/SprinklerStation.php";
require_once __DIR__ . "/../OpenSprinklerIO/module.php";

class OpenSprinklerStation extends BaseIPSModule
{
    const PROPERTY_Controller = "Controller";
    const PROPERTY_Index = "Index";

    const VARIABLE_Status = "Status";
    const VARIABLE_WeatherAdjusted = "WeatherAdjusted";
    const VARIABLE_Sensor1Enabled = "Sensor1Enabled";
    const VARIABLE_Sensor2Enabled = "Sensor2Enabled";

    const CMD_StationStatus = "StationStatus"; // Data darf ein Array sein oder ein einzelnes Objekt

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

        $receiveDataFilter = ".*\"Destination\":\"" . OpenSprinklerStation::class . "\".*";
        $this->SendDebug(__FUNCTION__, "ReceiveDataFilter=" . $receiveDataFilter, 0);
        $this->SetReceiveDataFilter($receiveDataFilter);

        try
        {

        }
        catch (Exception $e)
        {
            $this->SetStatus(201);
        }
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
        // VARIABLE_Sensor1Enabled und VARIABLE_Sensor2Enabled werden automatisch und nur dann erstellt, wenn am Controller vorhanden

        $this->EnableAction(self::VARIABLE_WeatherAdjusted);
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
            "onClick" => "OpenSprinkler_EnableStation(\$id, true);"
        ];

        $actions[] = [
            "type" => "Button",
            "label" => "Disable",
            "onClick" => "OpenSprinkler_EnableStation(\$id, false);"
        ];

        $actions[] = [
            "type" => "Button",
            "label" => "Start (30s)",
            "onClick" => "OpenSprinkler_SwitchStation(\$id, true, 30);"
        ];

        $actions[] = [
            "type" => "Button",
            "label" => "Stop",
            "onClick" => "OpenSprinkler_SwitchStation(\$id, false, 0);"
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
        $this->SendDebug(__FUNCTION__, "msg=" . print_r($msg, true), 0);

        $command = $msg->{OpenSprinklerIO::MSGARG_Command};

        switch ($command)
        {
            case self::CMD_StationStatus:
                $data = $msg->{OpenSprinklerIO::MSGARG_Data};

                $stationIndex = $this->ReadPropertyInteger(self::PROPERTY_Index);

                if (is_array($data))
                {
                    foreach ($data as $station)
                    {
                        if ($station->Index == $stationIndex)
                            $this->UpdateVariables($station);
                    }
                }
                else if (is_object($data))
                {
                    $this->UpdateVariables($data);
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

            case self::VARIABLE_Sensor1Enabled:
                // TBD
                break;

            case self::VARIABLE_Sensor2Enabled:
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
            if ($this->GetIDForIdent(self::VARIABLE_Sensor1Enabled) === false)
                $this->RegisterVariableBoolean(self::VARIABLE_Sensor1Enabled, $this->Translate("Sensor 1 Enabled"), "~Switch", 11);

            SetValueBoolean($this->GetIDForIdent(self::VARIABLE_Sensor1Enabled), $stationData->Sensor1Enabled);
            $this->EnableAction(self::VARIABLE_Sensor1Enabled);
        }

        if ($stationData->Sensor2Enabled == null)
        {
            $this->UnregisterVariable(self::VARIABLE_Sensor2Enabled);
        }
        else
        {
            if ($this->GetIDForIdent(self::VARIABLE_Sensor2Enabled) === false)
                $this->RegisterVariableBoolean(self::VARIABLE_Sensor2Enabled, $this->Translate("Sensor 2 Enabled"), "~Switch", 12);

            SetValueBoolean($this->GetIDForIdent(self::VARIABLE_Sensor2Enabled), $stationData->Sensor2Enabled);
            $this->EnableAction(self::VARIABLE_Sensor2Enabled);
        }
    }

    public function EnableStation(bool $enable)
    {
        $sendData = [
            'DataID' => OpenSprinklerIO::MODULE_GUID_RX,
            'Command' => OpenSprinklerIO::CMD_EnableStation,
            OpenSprinklerIO::CMDPARAM_Index => $this->ReadPropertyInteger(self::PROPERTY_Index),
            OpenSprinklerIO::CMDPARAM_Enable => $enable
        ];

        $this->SendDataToParent(json_encode($sendData));
    }

    public function SwitchStation(bool $enable, int $duration)
    {
        $sendData = [
            'DataID' => OpenSprinklerIO::MODULE_GUID_RX,
            'Command' => OpenSprinklerIO::CMD_SwitchStation,
            OpenSprinklerIO::CMDPARAM_Index => $this->ReadPropertyInteger(self::PROPERTY_Index),
            OpenSprinklerIO::CMDPARAM_Enable => $enable,
            OpenSprinklerIO::CMDPARAM_Duration => $duration
        ];

        $this->SendDataToParent(json_encode($sendData));
    }

    public function RequestStationStatus()
    {
        $sendData = [
            'DataID' => OpenSprinklerIO::MODULE_GUID_RX,
            'Command' => OpenSprinklerIO::CMD_RequestStationStatus,
            OpenSprinklerIO::CMDPARAM_Index => $this->ReadPropertyInteger(self::PROPERTY_Index)
        ];

        $this->SendDataToParent(json_encode($sendData));
    }
}

?>
