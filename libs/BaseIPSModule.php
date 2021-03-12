<?php

declare(strict_types=1);

class BaseIPSModule extends IPSModule
{
    const STRING_DateTimeFormat = "j.n.Y, H:i:s";

    public function Destroy()
    {
        // TBD: Läuft auf einen "InstanceInterface is not available" Fehler, wenn an die Stelle aufgerufen
        // $this->UnregisterAllMessages();

        parent::Destroy();
    }

    protected function UnregisterAllMessages()
    {
        $registeredMessages = $this->GetMessageList();

        if ($registeredMessages !== false && count($registeredMessages) > 0)
        {
            $this->SendDebug(__FUNCTION__, "Unregistering " . count($registeredMessages) . " message notifications", 0);

            foreach ($registeredMessages as $sender => $msgList) 
            {
                foreach ($msgList as $msg) 
                    $this->UnregisterMessage($sender, $msg);
            }
        }
    } 
}

?>
