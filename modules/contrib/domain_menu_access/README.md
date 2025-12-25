# Domain Menu Access

Domain Menu Access is an extension to
[Domain](https://www.drupal.org/project/domain) module, allowing administrators
to configure visitors' access to selected menu items based on current domain
they are viewing.

It lets administrators decide whether a specific menu item should be hidden on
selected domains (regardless of it being enabled by default using standard
Drupal functionality).

For a full description of the module, visit the
[project page](https://www.drupal.org/project/domain_menu_access).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/domain_menu_access).

## Requirements

This module requires the following modules:
- [Domain](https://www.drupal.org/project/domain)

This module supports the following modules:
- [Menu Block](https://www.drupal.org/project/menu_block)


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

You can enable domain control per menu on the admin settings page at
`admin/config/domain/domain_menu_access/config`.

Access to all menu items is managed on standard admin "Edit menu item" pages in
Admin / Structure / Menus, separately for each menu item, using domain
checkboxes in the "Domain" fieldset.

Now add a "Domain Menu" or "Domain Menu (Menu Block)" block with your menu. This
is necessary as the manipulators are only loaded using one of these blocks.
