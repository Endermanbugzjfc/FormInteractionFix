# Looking for testers and README improvers!
# Form Interaction Fix
[![CI](https://github.com/Endermanbugzjfc/FormInteractionFix/actions/workflows/main.yml/badge.svg)](https://github.com/Endermanbugzjfc/FormInteractionFix/actions/workflows/main.yml)
# Interaction-spam
Interaction spam is often a problem for players who use the mouse as their input.
Try to interact (right-click) while facing a block.
You may notice that PlayerInteractEvent is called many times in a short period.
If a plugin opens its form on interaction, these players will see the form opens more than once.

# The fix
This plugin sets interaction-spam protection for both forms and NPC dialogues.
By listening for packets and block interaction after a form or dialogue is sent to the player.
Until the player closes the form. For dialogues, until the server sent another close action.

# Compatibility
As this plugin is driven by packets, it works globally on the server.
Existing plugins that have no interaction cooldown will acquire the protection.
Future plugins can also choose to depend on this plugin instead of verbosely implementing an interaction cool down on their own.

# Disclaimer
Although I kept mentioning "protection" above, this plugin can not prevent any malicious attacks or behaviours.

[]