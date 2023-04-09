# SymconOpenSprinkler

SymconOpenSprinkler ist ein Erweiterungsmodul für IP-Symcon und dient dazu, einen OpenSprinkler Bewässerungscomputer zu überwachen und zu steuern.

ACHTUNG: Das Modul befindet sich im frühen Entwicklungsstadium! Es ist zu erwarten, dass Fehler auftreten.

### Inhalt

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Konfiguration](#4-konfiguration)
5. [Variablen und Variablenprofile](#5-variablen-und-variablenprofile)
6. [PHP-Befehlsreferenz](#6-php-befehlsreferenz)
7. [Anhang](#7-anhang)

### 1. Funktionsumfang

Derzeit ist folgende Basisfunktionalität implementiert:

__Controller__

- Statusanzeige
- Konfiguration Regenverzögerung
- Programm starten

__Sprinkler Station__

- Statusanzeige
- Konfiguration (Aktiviert/Deaktiviert, Wettergesteuert, Nacheinander)
- Starten und Stoppen

__Offene Punkte__

- Variable zum Schalten über UI
- Error handling
- Test für Geräte mit zweitem Board (Sprinkleranzahl > 8)

### 2. Voraussetzungen

- IP-Symcon ab Version 4.0
- OpenSprinkler (Hardware oder Raspi mit Erweiterungsboard)

### 3. Installation

Die Einrichtung erfolgt über die Modulverwaltung von Symcon.

Über das Modul-Control folgende URL hinzufügen: `git://github.com/bernd70/SymconOpenSprinkler.git`

Danach können OpenSprinkler Instanzen erstellt werden.

### 4. Konfiguration

OpenSprinklerIO Instanz anlegen mit Adresse (Name oder IP) und Gerätepasswort des Sprinkler Controllers sowie dem Pollig Intervall.
OpenSprinkklerController Instanz anlegen. Alle Sprinkler sollten automatisch gefunden werden und können als Instanzen in der definierbaren Kategorie erstellt werden.

### 5. Variablen und Variablenprofile

Die Variablen und Variablenprofile werden automatisch angelegt.

#### Variablen

Die zur Verfügung stehende Variablen werden zyklisch aktualisiert.

#### Variablenprofile

__OpenSprinkler.StationStatus__

Wert | Bezeichnung     | Anmerkung
---- | --------------- | -----------------
0    | Unbekannt       |
1    | Deaktiviert     |
2    | Leerlauf        |
3    | Geplant         |
4    | Aktiv           |

__OpenSprinkler.WeatherMethod__

Wert | Bezeichnung                        | Anmerkung
---- | ---------------------------------- | ------------------
0    | Manuelle Steuerung                 |
1    | Zimmermann                         |
2    | Automatische Verzögerung bei Regen |
3    | Evapotranspiration                 |

__OpenSprinkler.SensorType__

Wert | Bezeichnung     | Anmerkung
---- | --------------- | -----------------
0    | Nicht aktiv     |
1    | Regen           |
2    | Durchfluss      |
3    | Bodenfeuchte    |
240  | Programm        |

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
OpenSprinkler_SetRainDelay(integer $hours);
```
Aktiviert die Regenverzögerung für die angegebene Anzahl von Stunden. Bei $hours = 0 wird die Regenverzögerung ausgeschaltet.

```php
OpenSprinkler_GetStationIndex(integer $constrollerInstanceId, string $name) : int;
```
Liefert den Index einer Bewässerungsstation für einen bestimmten Controller über den Namen. Groß- und Kleinschreibung wird nicht beachtet.

```php
OpenSprinkler_UpdateStatus(integer $constrollerInstanceId);
```
Liest den Controller neu aus und aktualisiert die IP-Symcon Variablen. Der Aufruf erfolgt zyklisch und muss nicht manuell erfolgen.


### 7. Versioninformation

#### v1.0

- Initial Release

#### v1.1

- Option Serialized wird nicht mehr unterstützt, da sie ab OpenSprinkler Firmware 2.20 nicht mehr vorhanden ist.
- Firmware wind in Controller Configuration mit ausgegeben

