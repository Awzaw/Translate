#### General

A PocketMine plugin to translate chat in real time using BING's free Translation API.

Microsoft allow up to 2 million characters per month for free, beyond that you will be asked
to upgrade from the FREE AZURE subscription, or disable the plugin until your quota is reset.

#### Installation

First get an API key: https://azure.microsoft.com/en-us/pricing/details/cognitive-services/translator-text-api/
You'll need to create an AZURE account, prove your identity using a Credit Card (NOT FOR PAYMENT)
and follow the steps to get an API key. Full sign up instructions are to be found here : https://docs.microsoft.com/en-us/azure/cognitive-services/translator/translator-text-how-to-signup

Next, drop the .phar file into your plugins folder, restart the server, then enter your API-Key between the quotes
in the config.yml that was created in the 'plugins/Translate' folder. Restart again, and you should be up and running.


#### Language Codes
"ar, de, en, fi, fr, he, id, it, ja, ko, es, ru"

For the complete list please see https://msdn.microsoft.com/en-us/library/hh456380.aspx

#### Examples

`/translate` or `/tra` to start translation into the default server language

Type `/tra fr` to start translation into french

Type `/tra` again to turn off translation

Type `/tra list` to list available language codes


#### Commands

`/translate (/tra) [language code]` : toggles translation on/off to a language

`/translate list | help` : list available language codes


#### Permissions

`translate`

defaults to all players, use negative permission to forbid usage
