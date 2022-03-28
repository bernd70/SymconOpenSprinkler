# SymconOpenSprinkler

SymconOpenSprinkler ist ein Erweiterungsmodul für IP-Symcon und dient dazu, einen OpenSprinkler Bewässerungscomputer zu steuern.

Fragen und Diskussion zum Modul bitte im Symcon Forum. [Link zum Thread](https://community.symcon.de/tbd)

### Inhalt

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Konfiguration](#4-konfiguration)
5. [Variablen und Variablenprofile](#4-variablen-und-variablenprofile)
6. [PHP-Befehlsreferenz](#5-php-befehlsreferenz)
7. [Anhang](#6-anhang)

### 1. Funktionsumfang

Derzeit ist folgende Basisfunktionalität implementiert:

- tbd

### 2. Voraussetzungen

- IP-Symcon ab Version 4.0
- OpenSprinkler (Hardware oder Raspi mit Erweiterungsboard)

### 3. Installation

Die Einrichtung erfolgt über die Modulverwaltung von Symcon.

Über das Modul-Control folgende URL hinzufügen: `git://github.com/bernd70/SymconOpenSprinkler.git`

Danach können OpenSprinkler Instanzen erstellt werden.

##### Besonderheiten beim Update

__Vx.x --> Vy.y__

- tbd

### 4. Konfiguration

__Konfigurationsseite__

Name                           | Beschreibung
------------------------------ | ----------------------------------------------
tbd                            | tbd

### 5. Variablen und Variablenprofile

Die Variablen und Variablenprofile werden automatisch angelegt.

#### Variablen

Die nachfolgenden Variablen stehen zur Verfügung und werden zyklisch aktualisiert. Teilweise besteht eine Voraussetzung für das Lesen der Information.

Name          | Typ                                 | Beschreibung                            | Lese-Voraussetzung       | Anmerkung
------------- | ----------------------------------- | --------------------------------------- | ------------------------ | ----------------------------------
tbd           | String                              | tbd                                     |                          | tbd

#### Variablenprofile

__OpenSprinkler.tbd__

Wert | Bezeichnung     | Anmerkung
---- | --------------- | -----------------
0    | tbd             | tbd


### 6. PHP-Befehlsreferenz

Soweit nicht anders angegeben, liefern die Funktionen keinen Rückgabewert.

```php
OpenSprinkler_EnableStation(integer $stationInstanceId, bool $enable);
```
Aktiviert oder deaktiviert eine Bewässerungsstation.

```php
OpenSprinkler_SwitchStation(integer $stationInstanceId, bool $enable, int $duration);
```
Schaltet eine einzelne Station für eine bestimmte Zeit (in Sekunden) ein oder aus. Bie Ausschalten wird der parameter $duration ignoriert.

```php
OpenSprinkler_StopAllStations(integer $constrollerInstanceId);
```
Stoppt alle Stationen an einem Controller. Auch bereits anstehende Läufe werden gelöscht.

```php
OpenSprinkler_RunProgram(integer $constrollerInstanceId, string $programName, bool $useWeather);
```
Startet ein Beregnungsprogramm über den Namen. Zusätzlich kann festgelegt werden, ob die wetterabhängige Steuerung genuzt werden soll.

```php
OpenSprinkler_GetStationIndex(integer $constrollerInstanceId, string $name) : int;
```
Liefert den Index einer Bewässerungsstation für einen bestimmten Controller über den Namen. Groß- und Kleinschreibung wird nicht beachtet.

```php
OpenSprinkler_UpdateStatus(integer $constrollerInstanceId);
```
Liest den Controller neu aus und aktualisiert die IP-Symcon Variablen. Der Aufruf erfolgt zyklisch und muss nicht manuell erfolgen.

