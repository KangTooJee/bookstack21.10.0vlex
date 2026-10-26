# Đối chiếu đường dẫn VLEX / BookStack 21.11.1

Danh sách theo bản chép người dùng cung cấp. `existing`: giữ file hiện có; `compatibility-alias/loader`: dùng lại mã gốc; `scaffold`: chờ mã VLEX; `inactive-migration/runtime`: chưa kích hoạt; `reference-copy`: sao chép tài liệu/mẫu của BookStack; `asset-placeholder`: ảnh giữ chỗ; `unresolved`: chưa rõ tên/nội dung.

| Đường dẫn trong source/BookStack-21.11.1 | Trạng thái | Ghi chú |
| --- | --- | --- |
| `app/Actions/Activity.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Actions/ActivityService.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Actions/ActivityType.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Actions/Comment.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Actions/CommentRepo.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Actions/Favourite.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Actions/Tag.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Actions/TagRepo.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Actions/View.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Api/ApiDocGenerator.php` | scaffold | Chờ mã VLEX gốc; không được nối vào luồng chạy. |
| `app/Api/ApiToken.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Api/ApiTokenGuard.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Api/ListingResponseBuilder.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Auth/Guards/ApiTokenAuthenticationGuard.php` | compatibility-alias | app/Api/ApiTokenGuard.php |
| `app/Auth/Guards/ExternalBaseSessionGuard.php` | compatibility-alias | app/Auth/Access/Guards/ExternalBaseSessionGuard.php |
| `app/Auth/Guards/LdapSessionGuard.php` | compatibility-alias | app/Auth/Access/Guards/LdapSessionGuard.php |
| `app/Auth/Mfa/BackupCodeService.php` | compatibility-alias | app/Auth/Access/Mfa/BackupCodeService.php |
| `app/Auth/Mfa/MfaSession.php` | compatibility-alias | app/Auth/Access/Mfa/MfaSession.php |
| `app/Auth/Mfa/MfaView.php` | scaffold | Chờ mã VLEX gốc; không được nối vào luồng chạy. |
| `app/Auth/Mfa/TotpService.php` | compatibility-alias | app/Auth/Access/Mfa/TotpService.php |
| `app/Auth/Mfa/TotpValidationRule.php` | compatibility-alias | app/Auth/Access/Mfa/TotpValidationRule.php |
| `app/Auth/Old/OldAccessToken.php` | scaffold | Chờ mã VLEX gốc; không được nối vào luồng chạy. |
| `app/Auth/Old/OldToken.php` | scaffold | Chờ mã VLEX gốc; không được nối vào luồng chạy. |
| `app/Auth/Old/OldEmail… (tên bị mờ)` | unresolved | Tên không đọc rõ; không tự đoán tên file. |
| `app/Auth/Old/Old… (một số mục bị mờ)` | unresolved | Tên không đọc rõ; không tự đoán tên file. |
| `app/Auth/Old/OldService.php` | scaffold | Chờ mã VLEX gốc; không được nối vào luồng chạy. |
| `app/Auth/EmailConfirmationService.php` | compatibility-alias | app/Auth/Access/EmailConfirmationService.php |
| `app/Auth/ExternalBaseUserProvider.php` | compatibility-alias | app/Auth/Access/ExternalBaseUserProvider.php |
| `app/Auth/GroupSyncService.php` | compatibility-alias | app/Auth/Access/GroupSyncService.php |
| `app/Auth/LdapService.php` | compatibility-alias | app/Auth/Access/LdapService.php |
| `app/Auth/LoginService.php` | compatibility-alias | app/Auth/Access/LoginService.php |
| `app/Auth/RegistrationService.php` | compatibility-alias | app/Auth/Access/RegistrationService.php |
| `app/Auth/Saml2Service.php` | compatibility-alias | app/Auth/Access/Saml2Service.php |
| `app/Auth/SocialAuthService.php` | compatibility-alias | app/Auth/Access/SocialAuthService.php |
| `app/Auth/UserInviteService.php` | compatibility-alias | app/Auth/Access/UserInviteService.php |
| `app/Auth/UserTokenService.php` | compatibility-alias | app/Auth/Access/UserTokenService.php |
| `app/Permissions/EntityPermission.php` | compatibility-alias | app/Auth/Permissions/EntityPermission.php |
| `app/Permissions/JointPermission.php` | compatibility-alias | app/Auth/Permissions/JointPermission.php |
| `app/Permissions/PermissionService.php` | compatibility-alias | app/Auth/Permissions/PermissionService.php |
| `app/Permissions/PermissionRepo.php` | compatibility-alias | app/Auth/Permissions/PermissionsRepo.php |
| `app/Permissions/RolePermission.php` | compatibility-alias | app/Auth/Permissions/RolePermission.php |
| `app/Permissions/Role.php` | compatibility-alias | app/Auth/Role.php |
| `app/Permissions/SocialAccount.php` | compatibility-alias | app/Auth/SocialAccount.php |
| `app/Permissions/User.php` | compatibility-alias | app/Auth/User.php |
| `app/Permissions/UserRepo.php` | compatibility-alias | app/Auth/UserRepo.php |
| `app/Config/app.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Config/auth.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Config/broadcasting.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Config/cache.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Config/clockwork.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Config/database.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Config/debugbar.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Config/dompdf.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Config/filesystems.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Config/hashing.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Config/logging.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Config/mail.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Config/modules.php` | scaffold | Chờ mã VLEX gốc; không được nối vào luồng chạy. |
| `app/Config/oidc.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Config/queue.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Config/saml2.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Config/services.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Config/session.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Config/setting-defaults.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Config/snappy.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Config/view.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Console/Commands/CleanupImages.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Console/Commands/ClearActivity.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Console/Commands/ClearRevisions.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Console/Commands/ClearViews.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Console/Commands/CopyShelfPermissions.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Console/Commands/CreateAdmin.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Console/Commands/DeleteUsers.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Console/Commands/RegenerateCommentContent.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Console/Commands/RegeneratePermissions.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Console/Commands/RegenerateSearch.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Console/Commands/ResetMfa.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Console/Commands/UpdateLdap.php` | scaffold | Chờ nội dung file VLEX gốc; chưa suy đoán hành vi. |
| `app/Console/Commands/UpgradeDatabaseEncoding.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Console/Kernel.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Models/AttachedFile.php` | scaffold | Tài liệu gắn với thực thể VLEX; chờ schema và quan hệ gốc. |
| `app/Entities/Models/Book.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Models/BookChild.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Models/Bookshelf.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Models/Chapter.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Models/Deletion.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Models/Entity.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Models/Group.php` | scaffold | Nhóm người dùng do admin quản lý; chờ schema và quyền gốc. |
| `app/Entities/Models/HasCoverImage.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Models/Page.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Models/PageRevision.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Models/PermissionGroup.php` | scaffold | Liên kết quyền với nhóm; chờ schema gốc. |
| `app/Entities/Models/PermissionUser.php` | scaffold | Quyền theo người dùng; chờ schema gốc. |
| `app/Entities/Models/SearchTerm.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Queries/EntityQuery.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Queries/Popular.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Queries/RecentlyViewed.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Queries/TopFavourites.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Repos/AttachedFileRepo.php` | scaffold | Upload/download/move tài liệu; chờ API của mã VLEX gốc. |
| `app/Entities/Repos/BaseRepo.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Repos/BookRepo.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Repos/BookshelfRepo.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Repos/ChapterRepo.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Repos/GroupPermissionRepo.php` | scaffold | Quản lý quyền xem/thêm/sửa/xóa cho nhóm. |
| `app/Entities/Repos/GroupRepo.php` | scaffold | Quản lý nhóm người dùng; chưa nối vào quyền BookStack. |
| `app/Entities/Repos/PageRepo.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Repos/PermissionRepo.php` | scaffold | Phân quyền VLEX theo thực thể/người dùng/nhóm. |
| `app/Entities/Tools/Markdown/CustomListItemRenderer.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Tools/Markdown/CustomParagraphConverter.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Tools/Markdown/CustomStrikeThroughExtension.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Tools/Markdown/CustomStrikeThroughRenderer.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Tools/Markdown/HtmlToMarkdown.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Tools/BookContents.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Tools/ExportFormatter.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Tools/NextPreviousContentLocator.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Tools/PageContent.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Tools/PageEditActivity.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Tools/PermissionsUpdate.php` | compatibility-alias | app/Entities/Tools/PermissionsUpdater.php |
| `app/Entities/Tools/SearchIndex.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Tools/SearchOptions.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Tools/SearchResultsFormatter.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Tools/SearchRunner.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Tools/ShelfContext.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Tools/SiblingFinder.php` | compatibility-alias | app/Entities/Tools/SiblingFetcher.php |
| `app/Entities/Tools/SlugGenerator.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Tools/TrashCan.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Entities/Tools/BreadcrumbsViewComposer.php` | compatibility-alias | app/Entities/BreadcrumbsViewComposer.php |
| `app/Entities/Tools/EntityProvider.php` | compatibility-alias | app/Entities/EntityProvider.php |
| `app/Exceptions/AuthException.php` | scaffold | Chờ nội dung file VLEX gốc; chưa suy đoán hành vi. |
| `app/Exceptions/ConfirmationEmailException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/FileUploadException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/Handler.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/HttpFetchException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/ImageUploadException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/JsonDebugException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/LdapException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/LoginAttemptEmailNeededException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/LoginAttemptException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/MoveOperationException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/NotFoundException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/NotifyException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/OpenIdConnectException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/PermissionException.php` | scaffold | Chờ nội dung file VLEX gốc; chưa suy đoán hành vi. |
| `app/Exceptions/PrettyException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/SamlException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/SocialDriverNotConfigured.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/SocialSignInAccountNotUsed.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/SocialSignInException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/SortOperationException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/StoppedAuthenticationException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/UnauthorizedException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/UserRegistrationException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/UserTokenExpiredException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/UserTokenNotFoundException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/UserUpdateException.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Exceptions/WhoopsBookStackPrettyHandler.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Facades/Activity.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Facades/Permissions.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Facades/Theme.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/Api/ImporterV2Controller.php` | scaffold | API nhập tài liệu VLEX; chờ định dạng request gốc. |
| `app/Http/Controllers/Api/PageApiController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/Api/PageExportApiController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/Api/SearchApiController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/Auth/ConfirmEmailController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/Auth/ForgotPasswordController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/Auth/HandlesPartialLogins.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/Auth/LoginController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/Auth/MfaBackupCodesController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/Auth/MfaController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/Auth/MfaTotpController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/Auth/OidcController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/Auth/RegisterController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/Auth/ResetPasswordController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/Auth/Saml2Controller.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/Auth/SocialController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/Auth/UserInviteController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/Images/DrawImageController.php` | scaffold | Chờ nội dung file VLEX gốc; chưa suy đoán hành vi. |
| `app/Http/Controllers/Images/GalleryImageController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/Images/ImageController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/AttachmentController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/AuditLogController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/BookController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/BookExportController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/BookshelfController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/BookshelfSortController.php` | scaffold | Chờ nội dung file VLEX gốc; chưa suy đoán hành vi. |
| `app/Http/Controllers/ChapterController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/ChapterExportController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/CommentController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/Controller.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/FavouriteController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/FileAttachedController.php` | scaffold | Thao tác tệp trực tiếp trong sách/chương/trang BookStack. |
| `app/Http/Controllers/GroupController.php` | scaffold | Admin quản lý nhóm; chưa đăng ký route. |
| `app/Http/Controllers/HomeController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/ImportLibraryController.php` | scaffold | Nhập thư viện tài liệu vào cấu trúc BookStack. |
| `app/Http/Controllers/MaintenanceController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/PageController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/PageExportController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/PageRevisionController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/PageTemplateController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/PermissionsAdminController.php` | scaffold | Admin cấp quyền người dùng và nhóm; chưa đăng ký route. |
| `app/Http/Controllers/RecycleBinController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/RoleController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/SearchController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/SettingController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/StatusController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/TagController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/UserApiTokenController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/UserController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/UserProfileController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Controllers/UserSearchController.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Http/Kernel.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Notifications/ConfirmEmail.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Notifications/MailNotification.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Notifications/ResetPassword.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Notifications/TestEmail.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Notifications/UserInvite.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Providers/AppServiceProvider.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Providers/AuthServiceProvider.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Providers/BroadcastServiceProvider.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Providers/CustomFacadeServiceProvider.php` | scaffold | Chờ nội dung file VLEX gốc; chưa suy đoán hành vi. |
| `app/Providers/CustomMarkdownServiceProvider.php` | scaffold | Chờ nội dung file VLEX gốc; chưa suy đoán hành vi. |
| `app/Providers/EventServiceProvider.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Providers/PaginationServiceProvider.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Providers/RouteServiceProvider.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Providers/ThemeServiceProvider.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Providers/TranslationServiceProvider.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Settings/Setting.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Settings/SettingService.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Theming/CustomHtmlHeadContentProvider.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Theming/ThemeEvents.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Theming/ThemeService.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Traits/HasCreatorAndUpdater.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Traits/HasHtml.php` | scaffold | Chờ nội dung file VLEX gốc; chưa suy đoán hành vi. |
| `app/Translation/FileLoader.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Uploads/Attachment.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Uploads/AttachmentService.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Uploads/HttpFetcher.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Uploads/Image.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Uploads/ImageHash.php` | scaffold | Chờ nội dung file VLEX gốc; chưa suy đoán hành vi. |
| `app/Uploads/ImageService.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Uploads/UrlFetcher.php` | scaffold | Chờ nội dung file VLEX gốc; chưa suy đoán hành vi. |
| `app/Util/CsvEscape.php` | scaffold | Chờ nội dung file VLEX gốc; chưa suy đoán hành vi. |
| `app/Util/HtmlContentFilter.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Util/HtmlNonceApplicator.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Util/WebSafeMimeSniffer.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/ViewComponents/HierarchyFolder.php` | scaffold | Hiển thị cây thư mục cha/con trong giao diện BookStack. |
| `app/View/Helpers.php` | scaffold | Chờ nội dung file VLEX gốc; chưa suy đoán hành vi. |
| `app/View/LibraryMap.php` | scaffold | Ánh xạ cây thư viện VLEX với thực thể BookStack. |
| `app/View/SaveModule… (tên bị mờ)` | unresolved | Tên không đọc rõ; không tự đoán tên file. |
| `app/View/ZipFolder.php` | scaffold | Xuất cây thư mục thành ZIP; chỉ dùng thư viện sẵn có. |
| `app/Application.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/helpers.php` | existing | Giữ nguyên mã BookStack đang có. |
| `app/Model.php` | existing | Giữ nguyên mã BookStack đang có. |
| `database/seeds/DatabaseSeeder.php` | compatibility-loader | database/seeders/DatabaseSeeder.php |
| `database/seeds/DummyContentSeeder.php` | compatibility-loader | database/seeders/DummyContentSeeder.php |
| `database/seeds/LegacyContentSeeder.php` | scaffold | Chờ mã seeder VLEX; không tạo dữ liệu giả. |
| `database/migrations/2021_12_02_143028_create_attached_file.php.stub` | inactive-migration | Tên chuẩn hóa từ bản chép; chưa biết schema. Đuôi .stub ngăn migrate tự chạy. |
| `database/migrations/2021_12_29_064429_add_new_role_chapter_file.php.stub` | inactive-migration | Tên chuẩn hóa từ bản chép; chưa biết schema. Đuôi .stub ngăn migrate tự chạy. |
| `database/migrations/2022_01_15_034813_create_entity_permission_table.php.stub` | inactive-migration | Tên chuẩn hóa từ bản chép; chưa biết schema. Đuôi .stub ngăn migrate tự chạy. |
| `database/migrations/2022_01_15_034920_create_entity_permission_role_table.php.stub` | inactive-migration | Tên chuẩn hóa từ bản chép; chưa biết schema. Đuôi .stub ngăn migrate tự chạy. |
| `database/migrations/2022_01_15_035901_create_entity_permission_user_table.php.stub` | inactive-migration | Tên chuẩn hóa từ bản chép; chưa biết schema. Đuôi .stub ngăn migrate tự chạy. |
| `database/migrations/2022_02_25_070035_add_new_column_in_user_table.php.stub` | inactive-migration | Tên chuẩn hóa từ bản chép; chưa biết schema. Đuôi .stub ngăn migrate tự chạy. |
| `database/migrations/2022_02_25_070946_create_groups_table.php.stub` | inactive-migration | Tên chuẩn hóa từ bản chép; chưa biết schema. Đuôi .stub ngăn migrate tự chạy. |
| `database/migrations/2022_02_25_071406_create_permissions_table.php.stub` | inactive-migration | Tên chuẩn hóa từ bản chép; chưa biết schema. Đuôi .stub ngăn migrate tự chạy. |
| `database/migrations/2022_02_27_112844_create_group_user_table.php.stub` | inactive-migration | Tên chuẩn hóa từ bản chép; chưa biết schema. Đuôi .stub ngăn migrate tự chạy. |
| `database/migrations/2022_03_02_132433_create_permission_user_table.php.stub` | inactive-migration | Tên chuẩn hóa từ bản chép; chưa biết schema. Đuôi .stub ngăn migrate tự chạy. |
| `database/migrations/2022_03_11_071345_create_group_permission_table.php.stub` | inactive-migration | Tên chuẩn hóa từ bản chép; chưa biết schema. Đuôi .stub ngăn migrate tự chạy. |
| `database/migrations/2022_03_16_234953_add_type_to_users_table.php.stub` | inactive-migration | Tên chuẩn hóa từ bản chép; chưa biết schema. Đuôi .stub ngăn migrate tự chạy. |
| `database/migrations/2022_03_16_235625_insert_data_to_permissions_table.php.stub` | inactive-migration | Tên chuẩn hóa từ bản chép; chưa biết schema. Đuôi .stub ngăn migrate tự chạy. |
| `database/migrations/2014_10_12_000000_create_users_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2014_10_12_100000_create_password_resets_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2015_07_12_114933_create_books_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2015_07_12_190027_create_pages_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2015_07_13_172121_create_images_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2015_07_27_172342_create_chapters_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2015_08_08_200447_add_users_to_entities.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2015_08_09_093534_create_page_revisions_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2015_08_16_142133_create_activities_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2015_08_29_105422_add_roles_and_permissions.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2015_08_30_125859_create_settings_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2015_08_31_175240_add_search_indexes.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2015_09_04_165821_create_social_accounts_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2015_09_05_164707_add_email_confirmation_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2015_11_21_145609_create_views_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2015_11_26_221857_add_entity_indexes.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2015_12_05_145049_fulltext_weighting.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2015_12_07_195238_add_image_upload_types.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2015_12_09_195748_add_user_avatars.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2016_01_11_210908_add_external_auth_to_users.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2016_02_25_184030_add_slug_to_revisions.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2016_02_27_120329_update_permissions_and_roles.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2016_02_28_084200_add_entity_access_controls.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2016_03_09_203143_add_page_revision_types.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2016_03_13_082138_add_page_drafts.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2016_03_25_123157_add_markdown_support.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2016_04_09_100730_add_view_permissions_to_roles.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2016_04_20_192649_create_joint_permissions_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2016_05_06_185215_create_tags_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2016_07_07_181521_add_summary_to_page_revisions.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2016_09_29_101449_remove_hidden_roles.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2016_10_09_142037_create_attachments_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2017_01_21_163556_create_cache_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2017_01_21_163602_create_sessions_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2017_03_19_091553_create_search_index_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2017_04_20_185112_add_revision_counts.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2017_07_02_152834_update_db_encoding_to_ut8mb4.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2017_08_01_130541_create_comments_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2017_08_29_102650_add_cover_image_display.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2018_07_15_173514_add_role_external_auth_id.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2018_08_04_115700_create_bookshelves_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2019_07_07_112515_add_template_support.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2019_08_17_140214_add_user_invites_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2019_12_29_120917_add_api_auth.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2020_08_04_111754_drop_joint_permissions_id.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2020_08_04_131052_remove_role_name_field.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2020_09_19_094251_add_activity_indexes.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2020_09_27_210059_add_entity_soft_deletes.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2020_09_27_210528_create_deletions_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2020_11_07_232321_simplify_activities_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2020_12_30_173528_add_owned_by_field_to_entities.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2021_01_30_225441_add_settings_type_column.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2021_03_08_215138_add_user_slug.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2021_05_15_173110_create_favourites_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2021_06_30_173111_create_mfa_values_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2021_07_03_085038_add_mfa_enforced_to_roles_table.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2021_08_28_161743_add_export_role_permission.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2021_09_26_044614_add_activities_ip_column.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `database/migrations/2026_10_22_000001_create_vlex_workspace.php` | existing | Giữ lịch sử migration hiện có; không tạo bản trùng từ tên OCR sai. |
| `api/requests/attachments-create.json` | reference-copy | dev/api/requests/attachments-create.json |
| `api/requests/attachments-update.json` | reference-copy | dev/api/requests/attachments-update.json |
| `api/requests/books-create.json` | reference-copy | dev/api/requests/books-create.json |
| `api/requests/books-update.json` | reference-copy | dev/api/requests/books-update.json |
| `api/requests/chapters-create.json` | reference-copy | dev/api/requests/chapters-create.json |
| `api/requests/chapters-update.json` | reference-copy | dev/api/requests/chapters-update.json |
| `api/requests/pages-create.json` | reference-copy | dev/api/requests/pages-create.json |
| `api/requests/pages-update.json` | reference-copy | dev/api/requests/pages-update.json |
| `api/requests/shelves-create.json` | reference-copy | dev/api/requests/shelves-create.json |
| `api/requests/shelves-update.json` | reference-copy | dev/api/requests/shelves-update.json |
| `api/responses/attachments-create.json` | reference-copy | dev/api/responses/attachments-create.json |
| `api/responses/attachments-list.json` | reference-copy | dev/api/responses/attachments-list.json |
| `api/responses/attachments-read.json` | reference-copy | dev/api/responses/attachments-read.json |
| `api/responses/attachments-update.json` | reference-copy | dev/api/responses/attachments-update.json |
| `api/responses/books-create.json` | reference-copy | dev/api/responses/books-create.json |
| `api/responses/books-list.json` | reference-copy | dev/api/responses/books-list.json |
| `api/responses/books-read.json` | reference-copy | dev/api/responses/books-read.json |
| `api/responses/books-update.json` | reference-copy | dev/api/responses/books-update.json |
| `api/responses/chapters-create.json` | reference-copy | dev/api/responses/chapters-create.json |
| `api/responses/chapters-list.json` | reference-copy | dev/api/responses/chapters-list.json |
| `api/responses/chapters-read.json` | reference-copy | dev/api/responses/chapters-read.json |
| `api/responses/chapters-update.json` | reference-copy | dev/api/responses/chapters-update.json |
| `api/responses/pages-create.json` | reference-copy | dev/api/responses/pages-create.json |
| `api/responses/pages-list.json` | reference-copy | dev/api/responses/pages-list.json |
| `api/responses/pages-read.json` | reference-copy | dev/api/responses/pages-read.json |
| `api/responses/pages-update.json` | reference-copy | dev/api/responses/pages-update.json |
| `api/responses/search-all.json` | reference-copy | dev/api/responses/search-all.json |
| `api/responses/shelves-create.json` | reference-copy | dev/api/responses/shelves-create.json |
| `api/responses/shelves-list.json` | reference-copy | dev/api/responses/shelves-list.json |
| `api/responses/shelves-read.json` | reference-copy | dev/api/responses/shelves-read.json |
| `api/responses/shelves-update.json` | reference-copy | dev/api/responses/shelves-update.json |
| `api/requests/attachments-group.json` | scaffold | Chưa có payload VLEX gốc; {} là chỗ giữ, không phải request đã hỗ trợ. |
| `api/requests/search-all.json` | scaffold | Chưa có payload VLEX gốc; {} là chỗ giữ, không phải request đã hỗ trợ. |
| `docs/components.md` | reference-copy | Tài liệu BookStack gốc: dev/docs/components.md; tên legal cần đối chiếu VLEX. |
| `docs/legal-theme-system.md` | reference-copy | Tài liệu BookStack gốc: dev/docs/logical-theme-system.md; tên legal cần đối chiếu VLEX. |
| `docs/visual-theme-system.md` | reference-copy | Tài liệu BookStack gốc: dev/docs/visual-theme-system.md; tên legal cần đối chiếu VLEX. |
| `docker/initdb/.gitkeep` | directory | Giữ đường dẫn từ cây VLEX. |
| `public/images/.gitkeep` | directory | Giữ đường dẫn từ cây VLEX. |
| `public/js/.gitkeep` | directory | Giữ đường dẫn từ cây VLEX. |
| `public/public/.gitkeep` | directory | Giữ đường dẫn từ cây VLEX. |
| `app/Auth/Old/.gitkeep` | directory | Giữ đường dẫn từ cây VLEX. |
| `docker/Dockerfile.stub` | inactive-runtime | Không thay đổi môi trường Docker đang chạy. |
| `docker/entrypoint.sh.stub` | inactive-runtime | Không thay đổi môi trường Docker đang chạy. |
| `docker/entrypoint.redhat.sh.stub` | inactive-runtime | Không thay đổi môi trường Docker đang chạy. |
| `public/images/date-error.png` | asset-placeholder | PNG 1 pixel giữ đường dẫn; cần ảnh VLEX gốc, chưa gắn vào UI. |
| `public/images/document.png` | asset-placeholder | PNG 1 pixel giữ đường dẫn; cần ảnh VLEX gốc, chưa gắn vào UI. |
| `public/images/icons-add.png` | asset-placeholder | PNG 1 pixel giữ đường dẫn; cần ảnh VLEX gốc, chưa gắn vào UI. |
| `public/images/icons-reduce.png` | asset-placeholder | PNG 1 pixel giữ đường dẫn; cần ảnh VLEX gốc, chưa gắn vào UI. |
| `public/images/icons-microsoft-excel-24.png` | asset-placeholder | PNG 1 pixel giữ đường dẫn; cần ảnh VLEX gốc, chưa gắn vào UI. |
| `public/images/img-add-border.png` | asset-placeholder | PNG 1 pixel giữ đường dẫn; cần ảnh VLEX gốc, chưa gắn vào UI. |
| `public/images/img-add-history.png` | asset-placeholder | PNG 1 pixel giữ đường dẫn; cần ảnh VLEX gốc, chưa gắn vào UI. |
| `public/images/img-add-library.png` | asset-placeholder | PNG 1 pixel giữ đường dẫn; cần ảnh VLEX gốc, chưa gắn vào UI. |
| `public/images/img-add-resize.png` | asset-placeholder | PNG 1 pixel giữ đường dẫn; cần ảnh VLEX gốc, chưa gắn vào UI. |
| `public/images/sheet.png` | asset-placeholder | PNG 1 pixel giữ đường dẫn; cần ảnh VLEX gốc, chưa gắn vào UI. |
| `public/public/btn_search_group.png` | asset-placeholder | PNG 1 pixel giữ đường dẫn; cần ảnh VLEX gốc, chưa gắn vào UI. |
| `public/public/error.png` | asset-placeholder | PNG 1 pixel giữ đường dẫn; cần ảnh VLEX gốc, chưa gắn vào UI. |
| `public/public/error_2x.png` | asset-placeholder | PNG 1 pixel giữ đường dẫn; cần ảnh VLEX gốc, chưa gắn vào UI. |
| `public/public/error_3x.png` | asset-placeholder | PNG 1 pixel giữ đường dẫn; cần ảnh VLEX gốc, chưa gắn vào UI. |
| `public/public/Hide_show_pass.png` | asset-placeholder | PNG 1 pixel giữ đường dẫn; cần ảnh VLEX gốc, chưa gắn vào UI. |
| `public/public/logo2.png` | asset-placeholder | PNG 1 pixel giữ đường dẫn; cần ảnh VLEX gốc, chưa gắn vào UI. |
| `public/public/favicon.ico` | reference-copy | Bản sao public/favicon.ico; public/public không phải document root. |
| `public/public/loading.gif` | reference-copy | Bản sao public/loading.gif; public/public không phải document root. |
| `public/public/logo.png` | reference-copy | Bản sao public/logo.png; public/public không phải document root. |
| `public/public/user_avatar.png` | reference-copy | Bản sao public/user_avatar.png; public/public không phải document root. |
| `public/public/web.config` | reference-copy | Bản sao public/web.config; public/public không phải document root. |
| `public/public/index.php` | scaffold | Không tạo entry point thứ hai cho ứng dụng. |
| `.editorconfig` | scaffold | Thiết lập soạn thảo cục bộ. |
| `modules_statuses.json` | scaffold | Không có module ngoài được cài hoặc kích hoạt. |
| `requirements.txt` | scaffold | Không cài thêm thư viện. |
| `cache.sh` | scaffold | Dùng các lệnh artisan đã có, chạy thủ công trong container. |
| `.styleci.yml` | scaffold | Chưa tích hợp CI ngoài. |
| `public/images/Group … .png; public/public/Mark Group … .png; public/public/logo.gif` | unresolved | Tên ảnh nhóm và ảnh logo GIF chưa có nguồn gốc đầy đủ; không tự tạo thương hiệu giả. |
| `public/dist/*` | existing | Giữ assets BookStack có sẵn; không thay JS/CSS hay cài npm để mô phỏng tên file khác phiên bản. |
