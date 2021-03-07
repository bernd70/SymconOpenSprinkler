<?php

declare(strict_types=1);

require_once __DIR__ . "/../libs/BaseIPSModule.php";
require_once __DIR__ . "/../libs/SprinklerStation.php";
require_once __DIR__ . "/../OpenSprinklerIO/module.php";

class OpenSprinklerStation extends BaseIPSModule
{
    const PROPERTY_Index = "Index";

    const VARIABLE_Active = "Active";

    const VARIABLE_Enabled = "Enabled";
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

        // $stationIndex = $this->ReadPropertyInteger(self::PROPERTY_StationIndex);
        // $receiveDataFilter = '.*\"Index\": ' . $stationIndex . '.*';
        // $this->SendDebug(__FUNCTION__, "ReceiveDataFilter=" . $receiveDataFilter, 0);
        // $this->SetReceiveDataFilter($receiveDataFilter);

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
        // if (!IPS_VariableProfileExists("JvcProjectorControl.PowerStatus"))
        // {
        //     IPS_CreateVariableProfile("JvcProjectorControl.PowerStatus", 1);

        //     IPS_SetVariableProfileAssociation("JvcProjectorControl.PowerStatus", JvcProjectorConnection::POWERSTATUS_Unknown, $this->Translate("Unknown"), "", -1);
        //     IPS_SetVariableProfileAssociation("JvcProjectorControl.PowerStatus", JvcProjectorConnection::POWERSTATUS_Standby, $this->Translate("Standby"), "", -1);
        //     IPS_SetVariableProfileAssociation("JvcProjectorControl.PowerStatus", JvcProjectorConnection::POWERSTATUS_Starting, $this->Translate("Starting"), "", -1);
        //     IPS_SetVariableProfileAssociation("JvcProjectorControl.PowerStatus", JvcProjectorConnection::POWERSTATUS_PoweredOn, $this->Translate("Powered On"), "", -1);
        //     IPS_SetVariableProfileAssociation("JvcProjectorControl.PowerStatus", JvcProjectorConnection::POWERSTATUS_Cooldown, $this->Translate("Cooling down"), "", -1);
        //     IPS_SetVariableProfileAssociation("JvcProjectorControl.PowerStatus", JvcProjectorConnection::POWERSTATUS_Emergency, $this->Translate("Emergency"), "", -1);
        // }        
    }

    private function RegisterVariables()
    {
        $this->RegisterVariableBoolean(self::VARIABLE_Active, "Aktiv", "~Switch", 1);

        $this->RegisterVariableBoolean(self::VARIABLE_Enabled, "Enabled", "~Switch", 10);
        $this->RegisterVariableBoolean(self::VARIABLE_WeatherAdjusted, "WeatherAdjusted", "~Switch", 11);
        $this->RegisterVariableBoolean(self::VARIABLE_Sensor1Enabled, "Sensor 1 Enabled", "~Switch", 12);
        $this->RegisterVariableBoolean(self::VARIABLE_Sensor2Enabled, "Sensor 2 Enabled", "~Switch", 13);
        $this->RegisterVariableBoolean(self::VARIABLE_Serialized, "Serialized", "~Switch", 14);

        $this->EnableAction(self::VARIABLE_Active);

        $this->EnableAction(self::VARIABLE_Enabled);
        $this->EnableAction(self::VARIABLE_WeatherAdjusted);
        $this->EnableAction(self::VARIABLE_Sensor1Enabled);
        $this->EnableAction(self::VARIABLE_Sensor2Enabled);
        $this->EnableAction(self::VARIABLE_Serialized);
    }

    public function ReceiveData($jsonString)
    {
        // $this->SendDebug(__FUNCTION__, "data=" . $jsonString, 0);

        // Empfangene Daten vom IO Modul
        $jsonData = json_decode($jsonString, true);

        $this->ProcessMsg($jsonData["Buffer"]);
    }

    private function ProcessMsg($msg)
    {
        // $this->SendDebug(__FUNCTION__, "data=" . $msg, 0);

        $jsonData = json_decode($msg, true);

        $stationIndex = $this->ReadPropertyInteger(self::PROPERTY_Index);

        foreach ($jsonData as $station)
        {
            if ($station[SprinklerStation::KEY_Index] == $stationIndex)
            {
                $this->SendDebug(__FUNCTION__, "processing=" . print_r($station, true), 0);

                $this->UpdateVariables($station);
            }
        }
    }

    public function RequestAction($ident, $value)
    {
        $this->SendDebug(__FUNCTION__, "ident=" . $ident . ", value=" . $value, 0);

        switch ($ident)
        {
            case self::VARIABLE_Enabled:
                $this->Enable($value);
                break;
        }
    }    

    private function UpdateVariables($station)
    {
        SetValueBoolean($this->GetIDForIdent(self::VARIABLE_Active), $station[SprinklerStation::KEY_Active]);

        SetValueBoolean($this->GetIDForIdent(self::VARIABLE_Enabled), $station[SprinklerStation::KEY_Enabled]);
        SetValueBoolean($this->GetIDForIdent(self::VARIABLE_WeatherAdjusted), $station[SprinklerStation::KEY_WeatherAdjusted]);
        SetValueBoolean($this->GetIDForIdent(self::VARIABLE_Sensor1Enabled), $station[SprinklerStation::KEY_Sensor1Enabled]);
        SetValueBoolean($this->GetIDForIdent(self::VARIABLE_Sensor2Enabled), $station[SprinklerStation::KEY_Sensor2Enabled]);
        SetValueBoolean($this->GetIDForIdent(self::VARIABLE_Serialized), $station[SprinklerStation::KEY_Serialized]);
    }

    public function Enable(bool $enable)
    {
        $sendData = [
            'DataID' => OpenSprinklerIO::MODULE_GUID_RX, 
            'Command' => OpenSprinklerIO::CMD_EnableStation, 
            OpenSprinklerIO::CMDPARAM_Index => $this->ReadPropertyInteger(self::PROPERTY_Index),
            OpenSprinklerIO::CMDPARAM_Enable => $enable
        ];

        $this->SendDataToParent(json_encode($sendData));
    }

    public function Run(bool $enable, int $duration)
    {
        $sendData = [
            'DataID' => OpenSprinklerIO::MODULE_GUID_RX, 
            'Command' => OpenSprinklerIO::CMD_RunStation, 
            OpenSprinklerIO::CMDPARAM_Index => $this->ReadPropertyInteger(self::PROPERTY_Index),
            OpenSprinklerIO::CMDPARAM_Enable => $enable,
            OpenSprinklerIO::CMDPARAM_Duration => $duration
        ];

        $this->SendDataToParent(json_encode($sendData));
    }    
}

?>
