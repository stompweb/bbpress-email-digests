bbPress Email Digests
=====================

Every time a user posts a topic or reply on a bbPress forum an email is sent to all the users that are subscribed to the forum or topic. If you run a site with a large userbase then this presents two problems:

1. When a user posts a topic or reply they have to wait until all the emails have been sent before the page reloads.
2. Users receive an email for each topic or reply that they are subscribed to resulting in a high number of emails sent.

This plugin attempts to fix those two issues by doing the following:

1. Handles all processing in the background to speed posting up for the users.
2. Sends an hourly digest email with all topics and replies posted since the last email.

## Usage

* Activate Plugin

## About the plugin

This plugin removes the default emails that bbPress sends out for notifications and replaces them with the process in this plugin. There are no settings for this plugin to change the frequency of emails or the text that is sent out in emails. Please edit the code directly if you wish to change this.
