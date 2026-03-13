Folgendes soll in der neuen Version gemacht werden:
- im Dialog werden folgende Felder benötigt:
    - Zusätzliche Auswahl für Gruppe Gastro
    - Auswahl Kundengruppe in Gruppe Grossist ändern
    - Zusätzlich Auswahl für Gruppe Club
    - Eingabe für den Rabatt der die Gruppe Club erhält
    - Auswahl für die Sprache in der die Artikelbezeichnung auf Änderung geprüft wird
    - Auswahl des Landes für die MwSt Berechnung (gesetzt auf Default Country)
    - Auswahl für die Features
        - Alter
        - Jahrgang
        - VOL %
        - Region
        - Inhalt
        - Land
        - Destillerie
        - Abfüller
    - Eingabe der Gruppen ID für
        - Sorte
        - Diverses
        - Abfüller
        - Destillerien
        - Länder
- Den Schalter CSV prices include tax (gross) auf alle Preisfelder anwenden
- Import ab https://tools.worldofwhisky.ch:447/export/preisliste?token=9N4VVRMHZG4GMSC1BIVBIIRKWKVECRLV
  Zertifikatsfehler bitte ignorieren mm


  
- Daraus folgende Felder in Prestasho übertragen:
| Feld aus CSV | Entität und Feld im Prestashop |
 | Id | nichts |
 | Bezeichnung | Artikelbezeichnung |
 |	Nummer	PreisPrivat	PreisGastro	PreisGrossisten	Kategorie	Lagerbestand	Jahrgang	Alter	Abfueller	Volumen	Region	Innhalt	Land	Destillerie	Aktiv	Zyklus	Beschreibung	LetzteMutation	EAN	Rabatt	MwSt	ProductType	PreisEinkauf
