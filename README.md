# Internauten B2B Import (PrestaShop 1.7)

This module imports price data from a remote URL and creates or updates a specific price for each product. Products are matched by `reference` (import field `Nummer`). The special price uses the import field `PreisGrossisten` and is applied to customer group ID `5` by default.

## TODO

- [ ] Clarify Tax handling and set the correct price. Eg add Field in config dialog to change this
- [ ] Skip update if price is same
- [ ] Clarify report with ok (price is already set)

## Install

1. Copy the `internautenb2bimport` folder into your PrestaShop `modules` directory.
2. In the Back Office, go to Modules and install **Internauten B2B Import**.

## Configure

Open the module configuration page and set:

- **Import URL** (default is the provided export endpoint)
- **Customer group ID** (default `5`)
- **Cron token** (used to secure the cron endpoint)
- **HTTP timeout**
- **Enable debug logging** (logs reference and PreisGrossisten for each row)

Click **Save**.

## Run Import

- **Manual:** Click **Run import now** in the module configuration page.
- **Cron:** Call the cron URL displayed in the module configuration page.

## Notes

- Import supports JSON and CSV payloads. <-- testet only with CSV (Header, comma separatet fields and ouble-quote characters for text)
- `PreisGrossisten` is treated as a tax-excluded price.
- The module updates an existing specific price for the same product and group, or creates a new one.

## Sample CSV
```
Id,Bezeichnung,Nummer,PreisPrivat,PreisGastro,PreisGrossisten,Kategorie
4190,"Irgend etwas",123000.0000,55.0000,47.0000,47.9000,"Damen"
4191,"Noch ein Text",123001.0000,69.0000,56.0000,0.0000,"Herren"
```

## Release via GitHub Actions

How to create a new release that builds the ZIP and attaches it to the GitHub Release:

1. Ensure `CHANGELOG.md` contains a section for the tag, for example:

    ```md
    ## v1.2.3
    
    - Short description of the changes.
    ```

2. Commit your changes and push them to `main`.

3. Create a tag and push it to GitHub:

    ```bash
    git tag v1.2.3
    git push origin v1.2.3
    ```

4. GitHub Actions runs the workflow, creates `internautenb2bimport.zip`, and attaches it to the release.

Notes:

- The release text is taken from the matching section in `CHANGELOG.md`.
- If no section is present, commit messages are used as release notes automatically.

## Development

You can develop the module directly in the prestshop module folder. But not in the production environment! Make a copy to a test system of your production system.

You can find a short how to on Azure in [Readme of InternautenB2BOffer](https://github.com/internauten/InternautenB2BOffer?tab=readme-ov-file#development)

### Get Module and install it

1. git clone yor fork of this repo
    ```bash
    cd ~ 
    git clone https://github.com/yourgithub/InternautenB2BImport.git
    ```
2. set owner, goup and rights
    ```bash
    sudo chown -R www-data:www-data ~/InternautenB2BImport/internautenb2bimport
    ```
3. Create symlink and set group:owner
    ```bash
    sudo ln -s ~/InternautenB2BImport/internautenb2bimport /var/www/prestashop/modules/internautenb2bimport
    sudo chown -h www-data:www-data ~/InternautenB2BImport/internautenb2bimport
    sudo chown -h www-data:www-data /var/www/prestashop/modules/internautenb2bimport
    ```
4. Activate and configure Module in Prestashop   
    In Prestashop backend go to Module Manager / not installed Modules and install the module.

## License

Academic Free License (AFL 3.0)