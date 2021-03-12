<?php

declare(strict_types=1);

require_once __DIR__ . "/../libs/BaseIPSModule.php";
require_once __DIR__ . "/../libs/SprinklerStation.php";
require_once __DIR__ . "/../OpenSprinklerIO/module.php";

class OpenSprinklerStation extends BaseIPSModule
{
    const PROPERTY_Index = "Index";

    const VARIABLE_Status = "Status";

    const STATIONSTATUS_Unknown = 0;
    const STATIONSTATUS_Deactivated = 1;
    const STATIONSTATUS_Idle = 2;
    const STATIONSTATUS_Scheduled = 3;
    const STATIONSTATUS_Running = 4;

    const VARIABLE_WeatherAdjusted = "WeatherAdjusted";
    const VARIABLE_Sensor1Enabled = "Sensor1Enabled";
    const VARIABLE_Sensor2Enabled = "Sensor2Enabled";
    const VARIABLE_Serialized = "Serialized";

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
        $this->RegisterPropertyInteger(self::PROPERTY_Index, -1);
    }

    private function RegisterVariableProfiles()
    {
        if (!IPS_VariableProfileExists("OpenSprinkler.StationStatus"))
        {
            IPS_CreateVariableProfile("OpenSprinkler.StationStatus", 1);

            IPS_SetVariableProfileAssociation("OpenSprinkler.StationStatus", self::STATIONSTATUS_Unknown, $this->Translate("Unknown"), "", -1);
            IPS_SetVariableProfileAssociation("OpenSprinkler.StationStatus", self::STATIONSTATUS_Deactivated, $this->Translate("Deactivated"), "", -1);
            IPS_SetVariableProfileAssociation("OpenSprinkler.StationStatus", self::STATIONSTATUS_Idle, $this->Translate("Idle"), "", -1);
            IPS_SetVariableProfileAssociation("OpenSprinkler.StationStatus", self::STATIONSTATUS_Scheduled, $this->Translate("Scheduled"), "", -1);
            IPS_SetVariableProfileAssociation("OpenSprinkler.StationStatus", self::STATIONSTATUS_Running, $this->Translate("Running"), "", -1);
        }
    }

    private function RegisterVariables()
    {
        $this->RegisterVariableInteger(self::VARIABLE_Status, $this->Translate("Status"), "OpenSprinkler.StationStatus", 1);

        $this->RegisterVariableBoolean(self::VARIABLE_WeatherAdjusted, $this->Translate("WeatherAdjusted"), "~Switch", 10);
        $this->RegisterVariableBoolean(self::VARIABLE_Serialized, $this->Translate("Serialized"), "~Switch", 13);

        $this->EnableAction(self::VARIABLE_WeatherAdjusted);
        $this->EnableAction(self::VARIABLE_Serialized);
    }

    public function GetConfigurationForm()
    {
        $stationIndex = $this->ReadPropertyInteger(self::PROPERTY_Index);

        $elements = [];

        $elements[] = [
            "type" => "ExpansionPanel",
            "caption" => "Station Configuration",
            "items" =>
            [
                [
                    "type" => "Label",
                    "name" => "StationIndex",
                    "caption" => sprintf($this->Translate("Station Index: %s"), $stationIndex)
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
        $this->SendDebug(__FUNCTION__, "msg=" . print_r($msg, true), 0);

        $command = $msg->{OpenSprinklerIO::MSGARG_Command};

        switch ($command)
        {
            case OpenSprinklerIO::CMD_StationStatus:
                $stations = $msg->{OpenSprinklerIO::MSGARG_Data};

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

    private function UpdateVariables($station)
    {
        $this->SendDebug(__FUNCTION__, "index=$station->Index, data=" . print_r($station, true), 0);

        if (!$station->Enabled)
            SetValueInteger($this->GetIDForIdent(self::VARIABLE_Status), self::STATIONSTATUS_Deactivated);
        else if ($station->Active)
            SetValueInteger($this->GetIDForIdent(self::VARIABLE_Status), self::STATIONSTATUS_Running);
        else if ($station->ScheduledTime != 0)
            SetValueInteger($this->GetIDForIdent(self::VARIABLE_Status), self::STATIONSTATUS_Scheduled);
        else
            SetValueInteger($this->GetIDForIdent(self::VARIABLE_Status), self::STATIONSTATUS_Idle);

        SetValueBoolean($this->GetIDForIdent(self::VARIABLE_WeatherAdjusted), $station->WeatherAdjusted);

        if ($station->Sensor1Enabled == null)
        {
            $this->UnregisterVariable(self::VARIABLE_Sensor1Enabled);
        }
        else
        {
            $this->RegisterVariableBoolean(self::VARIABLE_Sensor1Enabled, $this->Translate("Sensor 1 Enabled"), "~Switch", 11);
            SetValueBoolean($this->GetIDForIdent(self::VARIABLE_Sensor1Enabled), $station->Sensor1Enabled);
        }

        if ($station->Sensor2Enabled == null)
        {
            $this->UnregisterVariable(self::VARIABLE_Sensor2Enabled);
        }
        else
        {
            $this->RegisterVariableBoolean(self::VARIABLE_Sensor2Enabled, $this->Translate("Sensor 2 Enabled"), "~Switch", 11);
            SetValueBoolean($this->GetIDForIdent(self::VARIABLE_Sensor2Enabled), $station->Sensor2Enabled);
        }

        SetValueBoolean($this->GetIDForIdent(self::VARIABLE_Serialized), $station->Serialized);
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
}

?>
