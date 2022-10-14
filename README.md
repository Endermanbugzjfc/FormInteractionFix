# Form Interaction Fix
[![CI](https://github.com/Endermanbugzjfc/FormInteractionFix/actions/workflows/main.yml/badge.svg)](https://github.com/Endermanbugzjfc/FormInteractionFix/actions/workflows/main.yml)
# Interaction-spam
Interaction spam is often a problem for players who use mouse as their input.
Try to interact (right-click) while facing a block.
You may notice that PlayerInteractEvent is called many times in a short period of time.
If a plugin opens its form on interaction, these players will see the form opens for more than once.

# The fix
This plugin sets an interaction-spam protection for both forms and NPC dialogues.
By listening 
