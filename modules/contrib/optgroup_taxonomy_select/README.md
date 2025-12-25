CONTENTS OF THIS FILE
---------------------

 * INTRODUCTION
 * REQUIREMENTS
 * INSTALLATION
 * CONFIGURATION


INTRODUCTION
------------
Provides a field widget exclusively for the term reference fields
with OptGroup option.

 * For a full description of the module visit:
   https://www.drupal.org/project/optgroup_taxonomy_select

 * To submit bug reports and feature suggestions, or to track changes visit:
   https://www.drupal.org/project/issues/optgroup_taxonomy_select


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

 * Install the module as you would normally install a contributed
   Drupal module. Visit https://www.drupal.org/node/1897420 for further
   information.
 * Recommended: Install with Composer:
   composer require 'drupal/optgroup_taxonomy_select'

CONFIGURATION
-------------

  * On `admin/structure/types` choose the content type for which you want to use
    Optgroup Term Select Widget, for example *Article*.
  * Select **Manage Form Display** or go to
    `admin/structure/types/manage/article/form-display`.
  * Choose a entity reference(Term) field you want to use and in widget settings
    select **Optgroup Term Select** as the widget.
