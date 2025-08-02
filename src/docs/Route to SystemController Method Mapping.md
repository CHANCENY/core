# Route to SystemController Method Mapping




## system.reports.errors
- Path: `/admin/reports`
- Controller Method: `admin_report_site_controller`




## system.error.page.denied
- Path: `/error/page/access-denied`
- Controller Method: `system_error_page_denied`




## system.user.filter.page
- Path: `/user/search/[search:string]/[content_name:string]/auto`
- Controller Method: `system_user_filter_auto_page`




## system.reference.filter.page
- Path: `/reference/field/filter`
- Controller Method: `system_reference_filter`




## user.account.login.form.route
- Path: `/user/login`
- Controller Method: `user_login_form_controller`




## user.account.google.redirect.route
- Path: `/user/oauth/google/access`
- Controller Method: `user_login_google_redirect_controller`




## user.account.github.redirect.route
- Path: `/user/oauth/github/access`
- Controller Method: `user_login_github_redirect_controller`




## user.account.logout.route
- Path: `/user/logout`
- Controller Method: `user_logout_controller`




## system.messager.toastify
- Path: `/system/messages`
- Controller Method: `toastify_controller`




## system.assets.loader
- Path: `/system/assets/file`
- Controller Method: `assets_loader_controller`




## system.people
- Path: `/admin/people`
- Controller Method: `people_controller`




## system.account.delete
- Path: `/user/[uid:int]/delete`
- Controller Method: `account_delete_controller`




## system.account.edit
- Path: `/user/[uid:int]/edit`
- Controller Method: `account_edit_controller`




## system.account.view
- Path: `/user/[uid:int]`
- Controller Method: `account_controller`




## system.account.profile.edit
- Path: `/user/[uid:int]/profile/edit`
- Controller Method: `account_profile_edit_controller`




## system.configuration
- Path: `/admin/config`
- Controller Method: `configuration_controller`




## system.configuration.basic
- Path: `/admin/config/system/site-information`
- Controller Method: `configuration_basic_site_controller`




## system.configuration.account
- Path: `/admin/config/people/accounts`
- Controller Method: `configuration_account_controller`




## system.configuration.smtp
- Path: `/admin/config/smtp`
- Controller Method: `configuration_smtp_controller`




## system.configuration.logger
- Path: `/admin/config/development/settings`
- Controller Method: `configuration_logger_controller`




## system.structure
- Path: `/admin/structure`
- Controller Method: `structure_controller`




## system.structure.content-type
- Path: `/admin/structure/types`
- Controller Method: `structure_content_type_controller`




## system.structure.content-type.form
- Path: `/admin/structure/types/new`
- Controller Method: `structure_content_type_form_controller`




## system.structure.content-type.edit
- Path: `/admin/structure/content-type/[machine_name:string]/edit`
- Controller Method: `content_type_edit_form_controller`




## system.structure.content-type.delete
- Path: `/admin/structure/content-type/[machine_name:string]/delete`
- Controller Method: `content_type_delete_controller`




## system.structure.content-type.manage
- Path: `/admin/structure/content-type/[machine_name:string]/manage`
- Controller Method: `content_type_manage_controller`




## system.structure.content-type.manage.field.new
- Path: `/admin/structure/content-type/[machine_name:string]/field/new`
- Controller Method: `content_type_manage_add_field_controller`




## system.structure.content-type.manage.field.type.new
- Path: `/admin/structure/content-type/[machine_name:string]/field/[type:string]/new`
- Controller Method: `content_type_manage_add_field_type_controller`




## system.structure.content-type.manage.field.edit
- Path: `/admin/structure/content-type/[machine_name:string]/field/[field_name:string]/edit`
- Controller Method: `content_type_manage_edit_field_controller`




## system.structure.content-type.manage.field.delete
- Path: `/admin/structure/content-type/[machine_name:string]/field/[field_name:string]/delete`
- Controller Method: `content_type_manage_delete_field_controller`




## system.structure.field.wrapper.field
- Path: `/admin/structure/content-type/[machine_name:string]/field/[field_name:string]/inner-fields`
- Controller Method: `content_structure_field_inner_manage_controller`




## system.structure.field.wrapper.field.inner.add
- Path: `/admin/structure/content-type/[machine_name:string]/field/[field_name:string]/add/inner/new`
- Controller Method: `content_structure_field_inner_add_controller`




## system.structure.field.wrapper.field.inner.edit
- Path: `/admin/structure/content-type/[machine_name:string]/field/[parent_name:string]/[field_name:string]/inner/edit`
- Controller Method: `content_structure_field_inner_edit_controller`




## system.structure.field.wrapper.field.inner.delete
- Path: `/admin/structure/content-type/[machine_name:string]/field/[parent_name:string]/[field_name:string]/inner/delete`
- Controller Method: `content_type_manage_delete_inner_field_controller`




## system.structure.content
- Path: `/admin/content`
- Controller Method: `content_content_admin_controller`




## system.structure.content.add
- Path: `/node/add`
- Controller Method: `content_content_node_add_controller`




## system.structure.content.form
- Path: `/node/add/[content_name:string]`
- Controller Method: `content_node_add_controller`




## system.structure.content.node
- Path: `/node/[nid:int]`
- Controller Method: `content_node_controller`




## system.structure.content.form.edit
- Path: `/node/[nid:int]/edit`
- Controller Method: `content_node_add_edit_controller`




## system.structure.content.form.delete
- Path: `/node/[nid:int]/delete`
- Controller Method: `content_node_add_delete_controller`




## system.structure.views
- Path: `/admin/structure/views`
- Controller Method: `content_views_controller`




## system.structure.views.add
- Path: `/admin/structure/views/add`
- Controller Method: `content_views_add_controller`




## system.structure.views.delete
- Path: `/admin/structure/views/view/[view_name:string]/delete`
- Controller Method: `content_views_view_delete_controller`




## system.structure.views.edit
- Path: `/admin/structure/views/view/[view_name:string]`
- Controller Method: `content_views_view_edit_controller`




## system.structure.views.display
- Path: `/admin/structure/views/view/[view_name:string]/displays`
- Controller Method: `content_views_view_display_controller`




## system.structure.views.display.edit
- Path: `/admin/structure/views/view/[view_name:string]/display/[display:string]/edit`
- Controller Method: `content_views_view_display_edit_controller`




## system.search.settings
- Path: `/admin/search/settings`
- Controller Method: `admin_search_settings`




## system.search.setting.add
- Path: `/admin/search/setting/new`
- Controller Method: `admin_search_settings_new`




## system.search.setting.edit
- Path: `/admin/search/settings/[key:string]/edit`
- Controller Method: `admin_search_settings_edit`




## system.search.setting.delete
- Path: `/admin/search/settings/[key:string]/delete`
- Controller Method: `admin_search_settings_delete`




## system.search.setting.configure
- Path: `/admin/search/settings/[key:string]/configure`
- Controller Method: `admin_search_settings_configure`




## system.search.setting.search
- Path: `/search/[key:string]`
- Controller Method: `admin_search_settings_search_page`




## system.integration.configure.rest
- Path: `/admin/integration/rest`
- Controller Method: `integration_configure_rest`




## system.integration.configure.rest.version
- Path: `/admin/integration/rest/version/[version_id:string]`
- Controller Method: `integration_configure_rest_version`




## system.integration.configure.rest.version.delete
- Path: `/admin/integration/rest/version/[version_id:string]/delete`
- Controller Method: `integration_configure_rest_version_delete`




## system.cron.manage
- Path: `/cron/manage`
- Controller Method: `cron_manage`




## system.cron.add
- Path: `/cron/add`
- Controller Method: `cron_add`



