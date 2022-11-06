# Looking for testers and README improvers!
# Form Interaction Fix
[![CI](https://github.com/Endermanbugzjfc/FormInteractionFix/actions/workflows/main.yml/badge.svg)](https://github.com/Endermanbugzjfc/FormInteractionFix/actions/workflows/main.yml)
# Interaction-spam
Interaction spam is often a problem for players who use the mouse as their input.
Try to interact (right-click) while facing a block.
You may notice that PlayerInteractEvent is called many times in a short period.
If a plugin opens its form on interaction, these players will see the form opens more than once.

# The fix
This plugin enables interaction-spam protection on forms at the moment.
NPC dialogues will be supported soon™.
By listening for packets and cancelling interaction events after a form is sent to the player.
Until the player closes the form.

# Potential problems
This plugin relies on packets very much. And can malfunction if a client does not respond with the correct packet. For instance, official clients would not open a form in that the JSON of its packet is invalid. Eventually, the client can neither react to any form nor make any interaction.

# Inclusiveness
As this plugin is driven by packets, it works globally on the server.
Existing plugins that have no interaction cooldown will acquire the protection.
Future plugins can also choose to depend on this plugin instead of verbosely implementing an interaction cool down on their own.

# Disclaimer
Although I kept mentioning "protection" above, this plugin can not prevent any malicious attacks or behaviours.

# Developer note
This plugin uses Await-Generator, a library that brings async/awaits features to PHP.

Do not worry when you see unusual code like `while (true)` and `yield`. It will not block the thread. Instead, `yield` can pause the code flow and make it behaves like concurrency.
