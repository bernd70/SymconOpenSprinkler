<?php

declare(strict_types=1);

require_once __DIR__ . "/../libs/BaseIPSModule.php";
require_once __DIR__ . "/../libs/SprinklerStation.php";
require_once __DIR__ . "/../OpenSprinklerIO/module.php";
require_once __DIR__ . "/../OpenSprinklerStation/module.php";

class OpenSprinklerConfig extends BaseIPSModule
{
    const PROPERTY_ImportCategory = "ImportCategory";

    public function Create()
    {
        // Diese Zeile nicht löschen
        parent::Create();

        $this->RegisterPropertyInteger(self::PROPERTY_ImportCategory, 0);

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

            foreach ($stations as $key => $station) 
            {
                $stationIndex = $station[SprinklerStation::KEY_Index];
                $stationName = $station[SprinklerStation::KEY_Name];

                $instanceId = $this->GetStationInstance("", $stationIndex);

                $addValue = [
                    "Name"        => $stationName,
                    "Index"       => $stationIndex,
                    "instanceID"  => $instanceId,
                    "create"      => [ 
                        "moduleID" => "{DE0EA757-F6F3-4CC7-3FD0-39622E94EB35}", 
                        "location" => $importCategoryLocation,
                        "name" => $stationName,
                        "configuration" => [
                            OpenSprinklerStation::PROPERTY_Index => $stationIndex
                        ]
                    ],                    
                ];

                $formStations[] = $addValue;
            }
        }

        $elements[] = [
            "name"      => "OpenSprinklerConfiguration",
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

    private function GetStations()
    {
        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);

            return [];
        }

        $sendData = ['DataID' => OpenSprinklerIO::MODULE_GUID_RX, 'Command' => OpenSprinklerIO::CMD_GetStations];
        $jsonStations = $this->SendDataToParent(json_encode($sendData));

        $this->SendDebug(__FUNCTION__, 'stations=' . $jsonStations, 0);

        return json_decode($jsonStations, true);
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
}

?>
