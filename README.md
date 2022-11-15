# <img src="https://github.com/Endermanbugzjfc/FormInteractionFix/blob/master/assets/icon.jpeg" width=75/ title="Credit: from windows93.net"> Form Interaction Fix
[![CI](https://github.com/Endermanbugzjfc/FormInteractionFix/actions/workflows/main.yml/badge.svg)](https://github.com/Endermanbugzjfc/FormInteractionFix/actions/workflows/main.yml)
[![Please click here to report a problem.](https://img.shields.io/github/issues-raw/Endermanbugzjfc/FormInteractionFix)](https://github.com/Endermanbugzjfc/FormInteractionFix/issues/new)
# Interaction spam
## Explanation
Interaction spam is often a problem for players who use the mouse as their input.
Try to interact (right-click) while facing a block.
You may notice that PlayerInteractEvent is called many times in a short period.
If a plugin opens its form on interaction, these players will see the form opens more than once.

## Problem demo (before installing)
![Before installing FormInteractionFix](https://github.com/Endermanbugzjfc/FormInteractionFix/blob/master/assets/problem.gif)

## Fix demo (after installing)
![After installing FormInteractionFix](https://github.com/Endermanbugzjfc/FormInteractionFix/blob/master/assets/fix.gif)

# The fix
This plugin blocks interaction when a form opens.
(NPC dialogues will be supported soon™.) By listening for packets and cancelling interaction events after a form is sent to the player.
Until the player responds to (or closes) it.

# Potential problems
This plugin relies on packets very much. And can malfunction if a client does not respond with the correct packet. For instance, official clients would not open a form in that the JSON of its packet is invalid. Eventually, the client can neither react to any form nor make any interaction.

# Inclusiveness
As this plugin is driven by packets, it works for all forms, no matter which plugin creates them.

# Disclaimer
This plugin cannot prevent any malicious attacks or behaviours.

# Developer note
## Depending on this plugin
Your plugin can depend on this plugin instead of verbosely implementing an interaction cool down on your own.

## await-generator & await-std
Both were made by [@SOF3](https://github.com/SOF3):
- [await-generator](https://github.com/SOF3/await-generator):  brings async/awaits to PHP. (Version ^3.4.3 is required because it fixes the [Traverser error.](https://github.com/SOF3/await-generator/pull/184))
- [await-std](https://github.com/SOF3/await-std): enables the use of PM API in async/await style, such as tasks and events.

Do not worry when you see unusual code like `while (true)` and `yield`. It will not block the thread. Instead, `yield` can pause the code flow and make it behaves like concurrency.

## IntegratedTest.php
Performs integrated tests with [FakePlayer by Muqsit](https://github.com/muqsit/fakeplayer). Please refer to the [doc comments](https://github.com/Endermanbugzjfc/FormInteractionFix/blob/b38e413e1de56660c4a2ba99e2f1498258cae3ad/IntegratedTest.php#L34-L48) in it for more details.

## PHPStan & PHP-CS-Fixer
Common PHP dev tools:
- [PHPStan](https://github.com/phpstan/phpstan): lint. (static code check.)
- [PHP-CS-Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer): fixes code standard. (Format code, replace FQCN with `use`, etc... Do not get confused with [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer)!)

# Credit
- Icon from https://windows93.net
- Thanks [@alvin0319](https://github.com/alvin0319) for providing me environment to perform live tests and record the demos.