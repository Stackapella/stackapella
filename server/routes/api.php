<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Account\AccountController;
use App\Http\Controllers\Api\Account\LearningController;
use App\Http\Controllers\Api\Admin\AuditController;
use App\Http\Controllers\Api\Admin\Beta\AssignmentsController as AdminBetaAssignments;
use App\Http\Controllers\Api\Admin\Beta\BarController as AdminBetaBar;
use App\Http\Controllers\Api\Admin\Beta\ClipsController as AdminBetaClips;
use App\Http\Controllers\Api\Admin\Beta\FeedbackController as AdminBetaFeedback;
use App\Http\Controllers\Api\Admin\Beta\GuidesController as AdminBetaGuides;
use App\Http\Controllers\Api\Admin\Beta\SingersController as AdminBetaSingers;
use App\Http\Controllers\Api\Admin\Beta\SubmissionsController as AdminBetaSubmissions;
use App\Http\Controllers\Api\Admin\BlocklistController;
use App\Http\Controllers\Api\Admin\BoardsController as AdminBoards;
use App\Http\Controllers\Api\Admin\BooksController as AdminBooks;
use App\Http\Controllers\Api\Admin\CoachDirectoryController as AdminCoachDirectory;
use App\Http\Controllers\Api\Admin\CoachingController as AdminCoaching;
use App\Http\Controllers\Api\Admin\DiagnosticsController as AdminDiagnostics;
use App\Http\Controllers\Api\Admin\DisputesController;
use App\Http\Controllers\Api\Admin\EarlyAccessController as AdminEarlyAccess;
use App\Http\Controllers\Api\Admin\EventScheduleController as AdminEventSchedule;
use App\Http\Controllers\Api\Admin\FlagsController;
use App\Http\Controllers\Api\Admin\FunnelController as AdminFunnel;
use App\Http\Controllers\Api\Admin\GalleryController as AdminGallery;
use App\Http\Controllers\Api\Admin\IdeasController as AdminIdeas;
use App\Http\Controllers\Api\Admin\LibraryController as AdminLibrary;
use App\Http\Controllers\Api\Admin\LimitsController;
use App\Http\Controllers\Api\Admin\MeController;
use App\Http\Controllers\Api\Admin\MudeoController as AdminMudeo;
use App\Http\Controllers\Api\Admin\NetworkController as AdminNetwork;
use App\Http\Controllers\Api\Admin\OverviewController;
use App\Http\Controllers\Api\Admin\PeopleController;
use App\Http\Controllers\Api\Admin\ReportsController as AdminReports;
use App\Http\Controllers\Api\Admin\SecurityEventsController;
use App\Http\Controllers\Api\Admin\SocialController as AdminSocial;
use App\Http\Controllers\Api\Admin\SupportController as AdminSupport;
use App\Http\Controllers\Api\Auth\AppAuthController;
use App\Http\Controllers\Api\Auth\AppDevicesController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\EarlyAccessController as EarlyAccessShow;
use App\Http\Controllers\Api\Auth\InviteLinkController as InviteLinkShow;
use App\Http\Controllers\Api\Auth\SignInLinkController;
use App\Http\Controllers\Api\Beta\AgreementController;
use App\Http\Controllers\Api\Beta\ConsentController;
use App\Http\Controllers\Api\Beta\FeedbackController;
use App\Http\Controllers\Api\Beta\InvitesController;
use App\Http\Controllers\Api\Beta\PartsController;
use App\Http\Controllers\Api\Beta\SubmissionsController;
use App\Http\Controllers\Api\Billing\BillingController;
use App\Http\Controllers\Api\Billing\RemindersController;
use App\Http\Controllers\Api\Billing\ScoresController;
use App\Http\Controllers\Api\Billing\StoreController;
use App\Http\Controllers\Api\Boards\BoardsController;
use App\Http\Controllers\Api\Books\BooksController;
use App\Http\Controllers\Api\Choirs\ChoirsController as ChoirsApi;
use App\Http\Controllers\Api\Coaching\CoachController;
use App\Http\Controllers\Api\Coaching\CoachesController;
use App\Http\Controllers\Api\Coaching\EngagementsController;
use App\Http\Controllers\Api\Coaching\ReviewsController;
use App\Http\Controllers\Api\Coaching\TeacherController;
use App\Http\Controllers\Api\Collaboration\CollaborationController;
use App\Http\Controllers\Api\Counts\CountsController;
use App\Http\Controllers\Api\Courses\ClipsController as CourseClipsController;
use App\Http\Controllers\Api\EventSchedule\ConnectionController;
use App\Http\Controllers\Api\Gallery\BellController as GalleryBell;
use App\Http\Controllers\Api\Gallery\ConsentsController as GalleryConsents;
use App\Http\Controllers\Api\Gallery\GalleryController as GalleryApi;
use App\Http\Controllers\Api\Gallery\OpenPartsController as OpenPartsApi;
use App\Http\Controllers\Api\Groups\GroupsController;
use App\Http\Controllers\Api\Groups\RehearsalsController;
use App\Http\Controllers\Api\Ideas\IdeasController as IdeasApi;
use App\Http\Controllers\Api\Leagues\LeaguesController as LeaguesApi;
use App\Http\Controllers\Api\Library\LibraryController as LibraryApi;
use App\Http\Controllers\Api\Projects\EditableController;
use App\Http\Controllers\Api\Projects\PartsController as ProjectParts;
use App\Http\Controllers\Api\Projects\ProjectsController;
use App\Http\Controllers\Api\Projects\SyncController;
use App\Http\Controllers\Api\Projects\TakesController;
use App\Http\Controllers\Api\Projects\UsageController;
use App\Http\Controllers\Api\Requests\RequestsController as RequestsApi;
use App\Http\Controllers\Api\Sharing\HarmoniesController;
use App\Http\Controllers\Api\Sharing\ReportsController as SharingReports;
use App\Http\Controllers\Api\Sharing\VideosController;
use App\Http\Controllers\Api\Support\SupportController;
use App\Http\Controllers\Api\Templates\FinishersController;
use App\Http\Controllers\Api\Templates\OwnTemplatesController;
use App\Http\Controllers\Api\TemplatesController;
use App\Http\Middleware\GrantBetaPass;
use App\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
|--------------------------------------------------------------------------
| The API (contract/api.md)
|--------------------------------------------------------------------------
|
| Every route here is an operation in contract/api.md, named after it (ContractTest checks both
| ways). The `api` group (bootstrap/app.php) is the session-cookie stack with CSRF, the app version
| check, read-only mode, the API limiter and no-store.
|
*/

// Accounts and sign-in (contract/api.md, "Accounts and sign-in").
Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::get('/config', [AuthController::class, 'config'])->name('config');
    Route::post('/register', [AuthController::class, 'register'])->middleware(['throttle:sign-up', 'shield'])->name('register');
    Route::post('/verify', [AuthController::class, 'verify'])->name('verify');
    Route::post('/link', [SignInLinkController::class, 'send'])->middleware(['throttle:sign-up', 'shield'])->name('link.send');
    Route::post('/link/open', [SignInLinkController::class, 'open'])->middleware('throttle:sign-in')->name('link.open');
    Route::post('/verification', [AuthController::class, 'resend'])->middleware('shield')->name('verification.resend');
    Route::post('/sign-in', [AuthController::class, 'signIn'])->middleware(['throttle:sign-in', 'shield'])->name('signin');
    Route::post('/sign-out', [AuthController::class, 'signOut'])->middleware('auth')->name('signout');
    Route::post('/sign-out-everywhere', [AuthController::class, 'signOutEverywhere'])->middleware('auth')->name('signout.everywhere');
    Route::post('/password/forgot', [AuthController::class, 'forgot'])->middleware(['throttle:password-reset', 'shield'])->name('password.forgot');
    Route::post('/password/reset', [AuthController::class, 'reset'])->middleware('throttle:password-reset-link')->name('password.reset');

    // The native apps (contract/api.md, "The apps: device tokens"): the same limits as the web's.
    Route::post('/app/sign-in', [AppAuthController::class, 'signIn'])->middleware(['throttle:sign-in', 'shield'])->name('app.signin');
    Route::post('/app/google', [AppAuthController::class, 'google'])->middleware(['throttle:provider-sign-in', 'shield'])->name('app.google');
    Route::post('/app/apple', [AppAuthController::class, 'apple'])->middleware(['throttle:provider-sign-in', 'shield'])->name('app.apple');
    Route::post('/app/verify', [AppAuthController::class, 'verify'])->name('app.verify');
    Route::post('/app/refresh', [AppAuthController::class, 'refresh'])->middleware('throttle:app-refresh')->name('app.refresh');
    Route::post('/app/sign-out', [AppAuthController::class, 'signOut'])->middleware('auth')->name('app.signout');
});

Route::post('/app-pass', [AppDevicesController::class, 'claimPass'])->middleware('throttle:invite-links')->name('appPass.claim');

Route::post('/account/email/confirm', [AccountController::class, 'confirmEmail'])->name('account.email.confirm');
Route::post('/account/email/revert', [AccountController::class, 'revertEmail'])->name('account.email.revert');

Route::middleware('auth')->prefix('account')->name('account.')->group(function (): void {
    Route::get('/', [AccountController::class, 'show'])->name('show');
    Route::get('/devices', [AppDevicesController::class, 'index'])->name('devices.list');
    Route::delete('/devices/{device}', [AppDevicesController::class, 'destroy'])->name('devices.delete');
    Route::put('/devices/current/push', [AppDevicesController::class, 'setPush'])->middleware('throttle:push-devices')->name('devices.push.update');
    Route::delete('/devices/current/push', [AppDevicesController::class, 'clearPush'])->name('devices.push.delete');
    Route::patch('/', [AccountController::class, 'update'])->name('update');
    Route::post('/terms', [AccountController::class, 'acceptTerms'])->name('terms.accept');
    Route::delete('/', [AccountController::class, 'destroy'])->name('delete');
    Route::get('/handle', [AccountController::class, 'handle'])->name('handle.check');
    Route::post('/email', [AccountController::class, 'email'])->middleware('throttle:email-change')->name('email.update');
    Route::put('/password', [AccountController::class, 'password'])->name('password.update');
    Route::delete('/methods/{method}', [AccountController::class, 'removeMethod'])->name('methods.delete');
    Route::post('/avatar', [AccountController::class, 'avatar'])->middleware('throttle:avatar')->name('avatar.update');
    Route::delete('/avatar', [AccountController::class, 'removeAvatar'])->name('avatar.delete');
    Route::post('/export', [AccountController::class, 'requestExport'])->middleware('throttle:data-export')->name('export.create');
    Route::get('/export', [AccountController::class, 'showExport'])->name('export.show');
});

// Projects and cloud save (contract/api.md, "Projects and cloud save").
Route::middleware('auth')->group(function (): void {
    Route::get('/sync', SyncController::class)->name('sync.pull');
    Route::get('/projects/{project}', [ProjectsController::class, 'show'])->whereUuid('project')->name('projects.show');
    Route::put('/projects/{project}', [ProjectsController::class, 'save'])->whereUuid('project')->name('projects.save');
    Route::delete('/projects/{project}', [ProjectsController::class, 'destroy'])->whereUuid('project')->name('projects.delete');
    Route::post('/projects/{project}/restore', [ProjectsController::class, 'restore'])->whereUuid('project')->name('projects.restore');
    Route::put('/parts/{part}', [ProjectParts::class, 'save'])->name('parts.save');
    Route::delete('/parts/{part}', [ProjectParts::class, 'destroy'])->name('parts.delete');
    Route::post('/takes', [TakesController::class, 'store'])->middleware(['age', 'throttle:take-uploads', 'paused:uploads'])->name('takes.create');
    Route::post('/takes/{take}/complete', [TakesController::class, 'complete'])->middleware('paused:uploads')->name('takes.complete');
    Route::get('/takes/{take}', [TakesController::class, 'show'])->name('takes.show');
    Route::post('/takes/{take}/restore', [TakesController::class, 'restore'])->name('takes.restore');
    Route::delete('/takes/{take}', [TakesController::class, 'destroy'])->name('takes.delete');
    Route::get('/parts/{part}/takes', [TakesController::class, 'forPart'])->name('parts.takes.list');
    Route::post('/parts/{part}/takes/{take}/use', [TakesController::class, 'use'])->name('parts.takes.use');
    Route::get('/bin', [UsageController::class, 'bin'])->name('bin.list');
    Route::delete('/bin/{kind}/{id}', [UsageController::class, 'purge'])->whereIn('kind', ['project', 'take'])->whereUuid('id')->name('bin.purge');
    Route::delete('/projects/{project}/older-takes', [UsageController::class, 'purgeTakes'])->whereUuid('project')->name('bin.purgeTakes');
    Route::get('/usage', [UsageController::class, 'show'])->name('usage.show');
    Route::get('/account/learning', [LearningController::class, 'show'])->name('account.learning.show');
    Route::put('/account/learning', [LearningController::class, 'update'])->name('account.learning.update');
    Route::post('/account/learning/reset', [LearningController::class, 'reset'])->name('account.learning.reset');
    Route::post('/account/learning/reset/undo', [LearningController::class, 'undoReset'])->name('account.learning.undoReset');
});

// Plus and billing (contract/api.md, "Plus and billing").
Route::get('/billing/prices', [BillingController::class, 'prices'])->middleware('revalidate')->name('billing.prices');
Route::middleware('auth')->group(function (): void {
    Route::get('/billing', [BillingController::class, 'show'])->name('billing.show');
    Route::post('/billing/checkout', [BillingController::class, 'checkout'])->middleware(['throttle:checkout', 'paused:checkout'])->name('billing.checkout');
    Route::post('/billing/store', [StoreController::class, 'sync'])->middleware('throttle:store-sync')->name('billing.store.sync');
    Route::post('/billing/portal', [BillingController::class, 'portal'])->middleware('throttle:checkout')->name('billing.portal');
    Route::put('/projects/editable', [EditableController::class, 'update'])->name('projects.editable.update');
    Route::post('/scores', [ScoresController::class, 'store'])->name('scores.create');
    Route::get('/scores', [ScoresController::class, 'index'])->name('scores.list');
    Route::get('/reminders', [RemindersController::class, 'show'])->name('reminders.show');
    Route::put('/reminders', [RemindersController::class, 'update'])->name('reminders.update');
    Route::post('/reminders/devices', [RemindersController::class, 'addDevice'])->middleware('throttle:push-devices')->name('reminders.devices.create');
});
// The service worker changes or removes its device by the device's token alone.
Route::put('/reminders/devices/{device}', [RemindersController::class, 'updateDevice'])->name('reminders.devices.update');
Route::delete('/reminders/devices/{device}', [RemindersController::class, 'removeDevice'])->name('reminders.devices.delete');

// Friends on a project (contract/api.md, "Collaboration"). The shared list is before
// /projects/{project}, which takes UUIDs only.
Route::middleware('auth')->group(function (): void {
    Route::get('/projects/shared', [CollaborationController::class, 'shared'])->name('projects.shared');
    Route::get('/projects/{project}/members', [CollaborationController::class, 'members'])->whereUuid('project')->name('projects.members.list');
    Route::delete('/projects/{project}/members/{member}', [CollaborationController::class, 'removeMember'])->whereUuid('project')->name('projects.members.delete');
    Route::post('/projects/{project}/invites', [CollaborationController::class, 'createInvite'])->whereUuid('project')->name('invites.create');
    Route::get('/projects/{project}/invites', [CollaborationController::class, 'invites'])->whereUuid('project')->name('invites.list');
    Route::delete('/invites/{invite}', [CollaborationController::class, 'revokeInvite'])->name('invites.revoke');
    Route::post('/join/{token}', [CollaborationController::class, 'acceptInvite'])->middleware(['age', 'throttle:invite-links'])->name('invites.accept');
});
Route::get('/join/{token}', [CollaborationController::class, 'showInvite'])->middleware('throttle:invite-links')->name('invites.show');

// The mudeo email's early-access link (docs/mudeo-transition-plan.md): whether it still lets people in.
Route::get('/early/{code}', EarlyAccessShow::class)->middleware('throttle:invite-links')->name('earlyAccess.show');
// Hillel's shared invite link: the same, for anyone he hands it to.
Route::get('/invite/{code}', InviteLinkShow::class)->middleware('throttle:invite-links')->name('inviteLink.show');

// Coaching, without the directory (contract/api.md, "Coaching").
Route::get('/coaches', [CoachesController::class, 'index'])->middleware('throttle:public-pages')->name('coaches.list');
Route::get('/coaches/{handle}', [CoachesController::class, 'show'])->name('coaches.show');
Route::get('/coaches/{handle}/offerings/{offering}/slots', [CoachesController::class, 'slots'])->middleware('throttle:lesson-slots')->name('coaches.slots');
Route::get('/teacher/{token}', [TeacherController::class, 'show'])->middleware('throttle:invite-links')->name('teacher.links.show');
Route::middleware('auth')->group(function (): void {
    Route::get('/coach/application', [CoachController::class, 'application'])->name('coach.application.show');
    Route::post('/coach/application', [CoachController::class, 'apply'])->middleware('throttle:coach-applications')->name('coach.application.create');
    Route::get('/coach/profile', [CoachController::class, 'profile'])->name('coach.profile.show');
    Route::patch('/coach/profile', [CoachController::class, 'updateProfile'])->name('coach.profile.update');
    Route::post('/coach/connect', [CoachController::class, 'connect'])->name('coach.connect.create');
    Route::get('/coach/connect', [CoachController::class, 'connectStatus'])->name('coach.connect.show');
    Route::get('/coach/offerings', [CoachController::class, 'offerings'])->name('coach.offerings.list');
    Route::post('/coach/offerings', [CoachController::class, 'createOffering'])->name('coach.offerings.create');
    Route::patch('/coach/offerings/{offering}', [CoachController::class, 'updateOffering'])->name('coach.offerings.update');
    Route::get('/coach/packs', [CoachController::class, 'packs'])->name('coach.packs.list');
    Route::put('/coach/packs/{pack}', [CoachController::class, 'savePack'])->whereUuid('pack')->name('coach.packs.save');
    Route::post('/coach/packs/{pack}/submit', [CoachController::class, 'submitPack'])->whereUuid('pack')->name('coach.packs.submit');
    Route::get('/packs/mine', [CoachesController::class, 'myPacks'])->name('packs.mine');
    Route::get('/coach/invoice-ninja', [BooksController::class, 'show'])->name('invoiceNinja.connection.show');
    Route::post('/coach/invoice-ninja', [BooksController::class, 'connect'])->middleware('throttle:books-connect')->name('invoiceNinja.connect');
    Route::patch('/coach/invoice-ninja', [BooksController::class, 'update'])->name('invoiceNinja.connection.update');
    Route::delete('/coach/invoice-ninja', [BooksController::class, 'destroy'])->name('invoiceNinja.connection.delete');
    Route::get('/coach/invoice-ninja/records', [BooksController::class, 'records'])->name('invoiceNinja.records.list');
    Route::post('/coach/invoice-ninja/records/{record}/retry', [BooksController::class, 'retry'])->name('invoiceNinja.records.retry');

    Route::post('/engagements', [EngagementsController::class, 'store'])->middleware(['age', 'throttle:checkout', 'paused:checkout'])->name('engagements.create');
    Route::get('/engagements', [EngagementsController::class, 'index'])->name('engagements.list');
    Route::get('/engagements/{engagement}', [EngagementsController::class, 'show'])->name('engagements.show');
    Route::post('/engagements/{engagement}/accept', [EngagementsController::class, 'accept'])->name('engagements.accept');
    Route::post('/engagements/{engagement}/decline', [EngagementsController::class, 'decline'])->name('engagements.decline');
    Route::post('/engagements/{engagement}/cancel', [EngagementsController::class, 'cancel'])->name('engagements.cancel');
    Route::post('/engagements/{engagement}/report', [EngagementsController::class, 'report'])->middleware('throttle:reports')->name('engagements.report');
    Route::post('/engagements/{engagement}/reviews', [EngagementsController::class, 'sendTake'])->name('reviews.create');
    Route::get('/engagements/{engagement}/messages', [EngagementsController::class, 'messages'])->name('messages.list');
    Route::post('/engagements/{engagement}/messages', [EngagementsController::class, 'message'])->middleware('throttle:coaching-messages')->name('messages.create');
    Route::post('/engagements/{engagement}/rating', [EngagementsController::class, 'rate'])->name('ratings.create');
    Route::put('/reviews/{review}', [ReviewsController::class, 'save'])->name('reviews.save');
    Route::post('/reviews/{review}/deliver', [ReviewsController::class, 'deliver'])->name('reviews.deliver');
    Route::post('/reviews/{review}/unsend', [ReviewsController::class, 'unsend'])->name('reviews.unsend');
    Route::post('/reviews/{review}/follow-up', [ReviewsController::class, 'followUp'])->name('reviews.followup');
    Route::post('/reviews/{review}/answer', [ReviewsController::class, 'answer'])->name('reviews.answer');
    Route::post('/reviews/{review}/media', [ReviewsController::class, 'startMedia'])->middleware(['throttle:take-uploads', 'paused:uploads'])->name('reviews.media.create');
    Route::post('/review-media/{media}/complete', [ReviewsController::class, 'completeMedia'])->middleware('paused:uploads')->name('reviews.media.complete');
    Route::post('/ratings/{rating}/reply', [ReviewsController::class, 'reply'])->name('ratings.reply');
    Route::post('/teacher-links', [TeacherController::class, 'store'])->name('teacher.links.create');
    Route::post('/teacher/{token}', [TeacherController::class, 'accept'])->middleware('throttle:invite-links')->name('teacher.links.accept');
});

// Choir groups (contract/api.md, "Choir groups").
Route::middleware('auth')->group(function (): void {
    Route::get('/groups', [GroupsController::class, 'index'])->name('groups.list');
    Route::post('/groups', [GroupsController::class, 'store'])->name('groups.create');
    Route::get('/groups/{group}', [GroupsController::class, 'view'])->name('groups.show');
    Route::patch('/groups/{group}', [GroupsController::class, 'update'])->name('groups.update');
    Route::delete('/groups/{group}', [GroupsController::class, 'destroy'])->name('groups.delete');
    Route::post('/groups/{group}/link', [GroupsController::class, 'link'])->name('groups.link.create');
    Route::delete('/groups/{group}/members/{member}', [GroupsController::class, 'removeMember'])->name('groups.members.delete');
    Route::post('/groups/{group}/songs', [GroupsController::class, 'addSong'])->name('groups.songs.create');
    Route::delete('/groups/{group}/songs/{template}', [GroupsController::class, 'removeSong'])->name('groups.songs.delete');
    Route::put('/groups/{group}/songs/{template}/assignments', [GroupsController::class, 'assign'])->name('groups.assignments.update');
    Route::post('/choir/{token}', [GroupsController::class, 'join'])->middleware(['age', 'throttle:invite-links'])->name('groups.join');
});
Route::get('/choir/{token}', [GroupsController::class, 'joinShow'])->middleware('throttle:invite-links')->name('groups.join.show');

// Event Schedule together (contract/api.md, "Event Schedule together (M9)").
Route::middleware('auth')->group(function (): void {
    Route::get('/event-schedule/connection', [ConnectionController::class, 'show'])->name('eventSchedule.connection.show');
    Route::post('/event-schedule/connect', [ConnectionController::class, 'start'])->middleware('throttle:event-schedule-connect')->name('eventSchedule.connect.create');
    Route::post('/event-schedule/connect/finish', [ConnectionController::class, 'finish'])->name('eventSchedule.connect.finish');
    Route::post('/event-schedule/connect/key', [ConnectionController::class, 'key'])->middleware('throttle:event-schedule-connect')->name('eventSchedule.connect.key');
    Route::patch('/event-schedule/connection', [ConnectionController::class, 'update'])->name('eventSchedule.connection.update');
    Route::delete('/event-schedule/connection', [ConnectionController::class, 'destroy'])->name('eventSchedule.connection.delete');
    Route::get('/event-schedule/schedules', [ConnectionController::class, 'schedules'])->name('eventSchedule.schedules.list');
    Route::get('/event-schedule/appointment-types', [ConnectionController::class, 'types'])->name('eventSchedule.types.list');
    Route::put('/groups/{group}/event-schedule', [RehearsalsController::class, 'link'])->name('groups.eventSchedule.update');
    Route::delete('/groups/{group}/event-schedule', [RehearsalsController::class, 'unlink'])->name('groups.eventSchedule.delete');
    Route::get('/groups/{group}/event-schedule/members', [RehearsalsController::class, 'members'])->name('groups.eventSchedule.members');
    Route::get('/groups/{group}/rehearsals', [RehearsalsController::class, 'index'])->name('groups.rehearsals.list');
    Route::put('/groups/{group}/rehearsals/{event}/songs', [RehearsalsController::class, 'songs'])->name('groups.rehearsals.songs');
});

// Sharing (contract/api.md, "Sharing: harmonies, videos and reports").
Route::middleware('auth')->group(function (): void {
    // A mix is an upload like a take: the same limiter, and paused with uploads.
    Route::post('/harmonies', [HarmoniesController::class, 'store'])->middleware(['age', 'throttle:take-uploads', 'paused:uploads'])->name('harmonies.create');
    Route::post('/harmonies/{harmony}/complete', [HarmoniesController::class, 'complete'])->middleware('paused:uploads')->name('harmonies.complete');
    Route::get('/harmonies', [HarmoniesController::class, 'index'])->name('harmonies.list');
    Route::get('/harmonies/{harmony}', [HarmoniesController::class, 'show'])->name('harmonies.show');
    Route::patch('/harmonies/{harmony}', [HarmoniesController::class, 'update'])->name('harmonies.update');
    Route::delete('/harmonies/{harmony}', [HarmoniesController::class, 'destroy'])->name('harmonies.delete');
    Route::post('/harmonies/{harmony}/videos', [VideosController::class, 'store'])->middleware('age')->name('videos.create');
    Route::get('/videos/{video}', [VideosController::class, 'show'])->name('videos.show');
});
// Support (contact and diagnostics) needs no account; notices are read by their private link too.
// A challenge link, before it's accepted: anyone holding it, signed in or not.
Route::get('/boards/challenges/{token}', [BoardsController::class, 'open'])->middleware('throttle:invite-links')->name('boards.challenges.show');
// Game mode's daily challenge: anyone sees the day's pick, the signed in its board.
Route::get('/boards/daily', [BoardsController::class, 'daily'])->middleware('throttle:public-pages')->name('boards.daily.show');
// Anonymous usage counts: no session, no CSRF (a beacon), nothing kept but the day's numbers.
Route::post('/counts', [CountsController::class, 'store'])->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class, GrantBetaPass::class])->middleware('throttle:counts')->name('counts.create');
Route::post('/support', [SupportController::class, 'store'])->middleware(['throttle:support', 'shield'])->name('support.create');
Route::get('/notices', [SupportController::class, 'notices'])->middleware('auth')->name('notices.list');
Route::get('/notices/{notice}', [SupportController::class, 'notice'])->middleware('throttle:invite-links')->name('notices.show');
Route::post('/notices/{notice}/appeal', [SupportController::class, 'appeal'])->middleware('throttle:invite-links')->name('notices.appeal');

// Reports need no account (the DSA's notice route), with the limiter and shield mode.
Route::post('/reports', SharingReports::class)->middleware(['throttle:reports', 'shield'])->name('reports.create');

// The gallery (N1; contract/api.md, "The gallery (N1)"): anyone reads it once it's open.
Route::get('/explore', [GalleryApi::class, 'explore'])->middleware(['throttle:public-pages', 'revalidate'])->name('explore.list');
Route::get('/profiles/{handle}', [GalleryApi::class, 'profile'])->middleware(['throttle:public-pages', 'revalidate'])->name('profiles.show');
Route::get('/profiles/{handle}/harmonies', [GalleryApi::class, 'profileHarmonies'])->middleware(['throttle:public-pages', 'revalidate'])->name('profiles.harmonies');
Route::get('/harmonies/{harmony}/comments', [GalleryApi::class, 'comments'])->middleware(['throttle:public-pages', 'revalidate'])->name('comments.list');
// Open parts (N5): parts left open, what a filler sings with, and the fills.
Route::get('/harmonies/{harmony}/fills', [OpenPartsApi::class, 'fills'])->middleware(['throttle:public-pages', 'revalidate'])->name('harmonies.fills.list');
Route::middleware('auth')->group(function (): void {
    Route::put('/harmonies/{harmony}/open', [OpenPartsApi::class, 'open'])->name('harmonies.open');
    Route::get('/harmonies/{harmony}/stems', [OpenPartsApi::class, 'stems'])->middleware(['age', 'throttle:stems'])->name('harmonies.stems');
});
Route::middleware('auth')->group(function (): void {
    Route::put('/harmonies/{harmony}/like', [GalleryApi::class, 'like'])->middleware('throttle:likes')->name('likes.create');
    Route::delete('/harmonies/{harmony}/like', [GalleryApi::class, 'unlike'])->name('likes.delete');
    Route::post('/harmonies/{harmony}/comments', [GalleryApi::class, 'comment'])->middleware(['throttle:comments', 'paused:comments'])->name('comments.create');
    Route::delete('/comments/{comment}', [GalleryApi::class, 'deleteComment'])->name('comments.delete');
    Route::put('/profiles/{handle}/follow', [GalleryApi::class, 'follow'])->middleware(['throttle:follows', 'paused:follows'])->name('follows.create');
    Route::delete('/profiles/{handle}/follow', [GalleryApi::class, 'unfollow'])->name('follows.delete');
    Route::put('/profiles/{handle}/block', [GalleryApi::class, 'block'])->name('blocks.create');
    Route::delete('/profiles/{handle}/block', [GalleryApi::class, 'unblock'])->name('blocks.delete');
    Route::get('/account/blocks', [GalleryApi::class, 'blocks'])->name('blocks.list');
    Route::post('/harmonies/{harmony}/consent', [GalleryConsents::class, 'ask'])->name('harmonies.consent.ask');
    Route::get('/consent-requests', [GalleryConsents::class, 'index'])->name('consentRequests.list');
    Route::post('/consent-requests/{consent}', [GalleryConsents::class, 'decide'])->name('consentRequests.decide');
    Route::get('/notifications', [GalleryBell::class, 'index'])->name('notifications.list');
    // Leagues and friends' leaderboards (N2).
    Route::get('/leagues/current', [LeaguesApi::class, 'current'])->name('leagues.current');
    Route::post('/leagues/membership', [LeaguesApi::class, 'join'])->name('leagues.join');
    Route::delete('/leagues/membership', [LeaguesApi::class, 'leave'])->name('leagues.leave');
    Route::get('/leagues/friends', [LeaguesApi::class, 'friends'])->name('friends.leaderboard');
    Route::post('/notifications/read', [GalleryBell::class, 'read'])->name('notifications.read');
    // Part leaderboards (docs/decisions.md, "part leaderboards"): free, signed in.
    Route::post('/boards/scores', [BoardsController::class, 'submit'])->middleware('throttle:board-scores')->name('boards.scores.submit');
    Route::get('/boards/{template}/{part}', [BoardsController::class, 'show'])->where('part', '[a-z0-9_-]{1,32}')->name('boards.show');
    Route::get('/boards/{template}/{part}/ghost', [BoardsController::class, 'ghost'])->where('part', '[a-z0-9_-]{1,32}')->name('boards.ghost.show');
    Route::put('/boards/{template}/{part}/ghost', [BoardsController::class, 'keepGhost'])->where('part', '[a-z0-9_-]{1,32}')->middleware('throttle:board-scores')->name('boards.ghost.update');
    Route::put('/boards/global', [BoardsController::class, 'global'])->name('boards.global.update');
    Route::post('/boards/challenges', [BoardsController::class, 'challenge'])->middleware('throttle:board-challenges')->name('boards.challenges.create');
    Route::post('/boards/challenges/{token}/accept', [BoardsController::class, 'accept'])->middleware('throttle:invite-links')->name('boards.challenges.accept');
});

// The templates people make (contract/api.md, "Your own templates"); before /templates/{template}.
Route::middleware('auth')->group(function (): void {
    Route::get('/templates/mine', [OwnTemplatesController::class, 'index'])->name('templates.mine');
    Route::put('/templates/{template}', [OwnTemplatesController::class, 'save'])->whereUuid('template')->name('templates.save');
    Route::patch('/templates/{template}', [OwnTemplatesController::class, 'update'])->whereUuid('template')->name('templates.update');
    Route::delete('/templates/{template}', [OwnTemplatesController::class, 'destroy'])->whereUuid('template')->name('templates.delete');
    Route::post('/templates/{template}/recordings', [OwnTemplatesController::class, 'startRecording'])->whereUuid('template')->middleware(['age', 'throttle:take-uploads', 'paused:uploads'])->name('templates.recordings.create');
    Route::post('/template-recordings/{recording}/complete', [OwnTemplatesController::class, 'completeRecording'])->middleware('paused:uploads')->name('templates.recordings.complete');
    Route::delete('/template-recordings/{recording}', [OwnTemplatesController::class, 'destroyRecording'])->name('templates.recordings.delete');
    // The template library (N3): publishing your own, and rating someone's.
    Route::put('/templates/{template}/publication', [LibraryApi::class, 'publish'])->whereUuid('template')->middleware('throttle:template-publish')->name('templates.publish');
    Route::delete('/templates/{template}/publication', [LibraryApi::class, 'unpublish'])->whereUuid('template')->name('templates.unpublish');
    Route::put('/templates/{template}/rating', [LibraryApi::class, 'rate'])->whereUuid('template')->middleware('throttle:likes')->name('templates.rate');
    Route::delete('/templates/{template}/rating', [LibraryApi::class, 'unrate'])->whereUuid('template')->name('templates.unrate');
});

// The catalogue: every song, for anyone.
Route::get('/templates', [TemplatesController::class, 'index'])->middleware('revalidate')->name('templates.catalogue');
// A course's real-voice clips (CourseClips), for its steps.
Route::get('/courses/{course}/clips', CourseClipsController::class)->where('course', '[a-z0-9-]{1,40}')->middleware('revalidate')->name('courses.clips.list');
Route::get('/library', [LibraryApi::class, 'index'])->middleware('throttle:public-pages')->name('library.list');

// The virtual choir (N6): anyone listens; joining signed in.
Route::get('/choirs/{song}', [ChoirsApi::class, 'show'])->where('song', '[a-z0-9-]{1,80}')->middleware(['throttle:public-pages', 'revalidate'])->name('choirs.show');
Route::get('/choirs/{song}/voices', [ChoirsApi::class, 'voices'])->where('song', '[a-z0-9-]{1,80}')->middleware(['throttle:public-pages', 'revalidate'])->name('choirs.voices.list');
Route::middleware('auth')->group(function (): void {
    Route::post('/choirs/{song}/voices', [ChoirsApi::class, 'join'])->where('song', '[a-z0-9-]{1,80}')->middleware(['age', 'throttle:idea-voices'])->name('choirs.join');
    Route::delete('/choirs/{song}/voices/mine', [ChoirsApi::class, 'leave'])->where('song', '[a-z0-9-]{1,80}')->name('choirs.leave');
});

// Song requests (N7): anyone reads them; asking and voting signed in.
Route::get('/requests', [RequestsApi::class, 'index'])->middleware('throttle:public-pages')->name('requests.list');
Route::middleware('auth')->group(function (): void {
    Route::post('/requests', [RequestsApi::class, 'store'])->middleware('throttle:comments')->name('requests.create');
    Route::put('/requests/{id}/vote', [RequestsApi::class, 'vote'])->middleware('throttle:likes')->name('requests.vote');
    Route::delete('/requests/{id}/vote', [RequestsApi::class, 'unvote'])->name('requests.unvote');
});

// Ideas (docs/your-tune-plan.md, step 3): the shelf for anyone; writing and singing signed in.
Route::get('/ideas', [IdeasApi::class, 'index'])->middleware(['throttle:public-pages', 'revalidate'])->name('ideas.list');
Route::middleware('auth')->group(function (): void {
    Route::get('/ideas/mine', [IdeasApi::class, 'mine'])->name('ideas.mine');
    Route::post('/ideas', [IdeasApi::class, 'store'])->middleware('throttle:ideas-create')->name('ideas.create');
    Route::delete('/ideas/{idea}', [IdeasApi::class, 'destroy'])->where('idea', '[A-Za-z0-9]{12}')->name('ideas.delete');
    Route::post('/ideas/{idea}/voices', [IdeasApi::class, 'startVoice'])->where('idea', '[A-Za-z0-9]{12}')->middleware(['age', 'throttle:idea-voices', 'paused:uploads'])->name('ideas.voices.create');
    Route::post('/idea-voices/{voice}/complete', [IdeasApi::class, 'completeVoice'])->whereUuid('voice')->middleware('paused:uploads')->name('ideas.voices.complete');
    Route::delete('/idea-voices/{voice}', [IdeasApi::class, 'destroyVoice'])->whereUuid('voice')->name('ideas.voices.delete');
});
Route::get('/ideas/{idea}', [IdeasApi::class, 'show'])->where('idea', '[A-Za-z0-9]{12}')->middleware(['throttle:public-pages', 'revalidate'])->name('ideas.show');
Route::get('/templates/{template}/finishers', FinishersController::class)->name('templates.finishers');
Route::get('/templates/{template}', [TemplatesController::class, 'show'])->name('templates.show');

// The beta singers: an invite attaches the singer to their own account; then their parts and takes.
Route::prefix('beta')->name('beta.')->group(function (): void {
    Route::get('/invites/{token}', [InvitesController::class, 'show'])->middleware('throttle:beta-link')->name('invite.show');
    Route::post('/invites/{token}/accept', [InvitesController::class, 'accept'])->middleware(['auth', 'throttle:beta-link'])->name('invite.accept');
    // Signed in: its blanks (the rate, the terms) are filled for the singers, not for anyone.
    Route::get('/agreement', AgreementController::class)->middleware('auth')->name('agreement.show');

    Route::middleware(['auth', 'beta'])->group(function (): void {
        Route::get('/me', [InvitesController::class, 'me'])->name('me.show');
        Route::post('/consent', [ConsentController::class, 'store'])->name('consent.create');
        Route::put('/credit', [ConsentController::class, 'update'])->name('credit.update');
        Route::get('/parts', PartsController::class)->name('parts.list');
        Route::post('/submissions', [SubmissionsController::class, 'store'])->middleware(['throttle:take-uploads', 'paused:uploads'])->name('submissions.create');
        Route::post('/submissions/{submission}/complete', [SubmissionsController::class, 'complete'])->middleware('paused:uploads')->name('submissions.complete');
        Route::post('/feedback', FeedbackController::class)->middleware('throttle:contact')->name('feedback.create');
    });
});

Route::middleware(['auth', 'editor', 'second-factor'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/me', MeController::class)->name('me');
    Route::get('/overview', OverviewController::class)->name('overview');

    // The beta review: editors work the queue; accepting, singers, guides and payment are the admin's.
    Route::prefix('beta')->name('beta.')->group(function (): void {
        Route::get('/submissions', [AdminBetaSubmissions::class, 'index'])->name('submissions.list');
        Route::get('/submissions/{submission}', [AdminBetaSubmissions::class, 'show'])->name('submissions.show');
        Route::post('/submissions/{submission}/open', [AdminBetaSubmissions::class, 'open'])->name('submissions.open');
        Route::post('/submissions/{submission}/retake', [AdminBetaSubmissions::class, 'retake'])->name('submissions.retake');
        Route::get('/guides', [AdminBetaGuides::class, 'index'])->name('guides.list');
        Route::get('/clips', AdminBetaClips::class)->name('clips.list');
        Route::get('/feedback', [AdminBetaFeedback::class, 'index'])->name('feedback.list');
        Route::post('/feedback/{feedback}/read', [AdminBetaFeedback::class, 'read'])->name('feedback.read');

        Route::middleware('admin')->group(function (): void {
            Route::get('/bar', [AdminBetaBar::class, 'show'])->name('bar.show');
            Route::patch('/bar', [AdminBetaBar::class, 'update'])->name('bar.update');
            Route::get('/singers', [AdminBetaSingers::class, 'index'])->name('singers.list');
            Route::post('/singers', [AdminBetaSingers::class, 'store'])->name('singers.create');
            Route::patch('/singers/{singer}', [AdminBetaSingers::class, 'update'])->name('singers.update');
            Route::post('/singers/{singer}/link', [AdminBetaSingers::class, 'link'])->name('singers.link');
            Route::post('/singers/{singer}/revoke', [AdminBetaSingers::class, 'revoke'])->name('singers.revoke');
            Route::post('/singers/{singer}/paid', [AdminBetaSingers::class, 'paid'])->middleware('sudo')->name('singers.paid');
            Route::post('/singers/{singer}/forget', [AdminBetaSingers::class, 'forget'])->middleware('sudo')->name('singers.forget');
            Route::post('/singers/{singer}/assignments', [AdminBetaAssignments::class, 'store'])->name('assignments.create');
            Route::delete('/assignments/{assignment}', [AdminBetaAssignments::class, 'destroy'])->name('assignments.delete');
            Route::patch('/submissions/{submission}', [AdminBetaSubmissions::class, 'adjust'])->name('submissions.adjust');
            Route::post('/submissions/{submission}/accept', [AdminBetaSubmissions::class, 'accept'])->name('submissions.accept');
            Route::post('/submissions/{submission}/reject', [AdminBetaSubmissions::class, 'reject'])->name('submissions.reject');
            Route::post('/submissions/{submission}/publish', [AdminBetaSubmissions::class, 'publish'])->name('submissions.publish');
            Route::post('/guides/{guide}/default', [AdminBetaGuides::class, 'default'])->name('guides.default');
            Route::delete('/guides/{guide}', [AdminBetaGuides::class, 'destroy'])->middleware('sudo')->name('guides.delete');
        });
    });

    // The trust and safety screen: the admin's alone; changes need a fresh second-factor check.
    Route::middleware('admin')->group(function (): void {
        Route::get('/flags', [FlagsController::class, 'index'])->name('flags.list');
        Route::put('/flags/{name}', [FlagsController::class, 'update'])->middleware('sudo')->name('flags.update');
        Route::get('/limits', [LimitsController::class, 'index'])->name('limits.list');
        Route::put('/limits/{limiter}/{rule}', [LimitsController::class, 'update'])->middleware('sudo')->name('limits.update');
        Route::get('/blocklist', [BlocklistController::class, 'index'])->name('blocklist.list');
        Route::post('/blocklist', [BlocklistController::class, 'store'])->middleware('sudo')->name('blocklist.create');
        Route::delete('/blocklist/{block}', [BlocklistController::class, 'destroy'])->middleware('sudo')->name('blocklist.delete');
        Route::get('/security-events', SecurityEventsController::class)->name('securityEvents.list');
        Route::get('/audit', AuditController::class)->name('audit.list');
        Route::get('/billing/disputes', DisputesController::class)->name('billing.disputes.list');
        Route::get('/reports', [AdminReports::class, 'index'])->name('reports.list');
        Route::get('/coaching/applications', [AdminCoaching::class, 'applications'])->name('coaching.applications.list');
        Route::post('/coaching/applications/{application}', [AdminCoaching::class, 'decide'])->name('coaching.applications.decide');
        Route::put('/coaching/coaches/{coach}', [AdminCoaching::class, 'coach'])->middleware('sudo')->name('coaching.coaches.update');
        Route::get('/coaching/packs', [AdminCoaching::class, 'packs'])->name('coaching.packs.list');
        Route::post('/coaching/packs/{pack}', [AdminCoaching::class, 'decidePack'])->name('coaching.packs.decide');
        Route::get('/coaching/disputes', [AdminCoaching::class, 'disputes'])->name('coaching.disputes.list');
        Route::post('/coaching/engagements/{engagement}/refund', [AdminCoaching::class, 'refund'])->middleware('sudo')->name('coaching.refund');
        Route::get('/event-schedule', AdminEventSchedule::class)->name('eventSchedule.show');
        Route::get('/early-access', [AdminEarlyAccess::class, 'show'])->name('earlyAccess.show');
        Route::patch('/early-access', [AdminEarlyAccess::class, 'update'])->name('earlyAccess.update');
        Route::post('/early-access/replace', [AdminEarlyAccess::class, 'replace'])->middleware('sudo')->name('earlyAccess.replace');
        Route::get('/preview', [AdminEarlyAccess::class, 'show'])->defaults('label', 'preview')->name('preview.show');
        Route::patch('/preview', [AdminEarlyAccess::class, 'update'])->defaults('label', 'preview')->name('preview.update');
        Route::post('/preview/replace', [AdminEarlyAccess::class, 'replace'])->defaults('label', 'preview')->middleware('sudo')->name('preview.replace');
        Route::get('/invite', [AdminEarlyAccess::class, 'show'])->defaults('label', 'invite')->name('invite.show');
        Route::patch('/invite', [AdminEarlyAccess::class, 'update'])->defaults('label', 'invite')->name('invite.update');
        Route::post('/invite/replace', [AdminEarlyAccess::class, 'replace'])->defaults('label', 'invite')->middleware('sudo')->name('invite.replace');
        Route::get('/mudeo', [AdminMudeo::class, 'show'])->name('mudeo.show');
        Route::patch('/mudeo', [AdminMudeo::class, 'update'])->middleware('sudo')->name('mudeo.update');
        Route::post('/mudeo/import', [AdminMudeo::class, 'import'])->middleware('sudo')->name('mudeo.import');
        Route::post('/mudeo/send', [AdminMudeo::class, 'start'])->middleware('sudo')->name('mudeo.send');
        Route::post('/mudeo/test', [AdminMudeo::class, 'test'])->name('mudeo.test');
        Route::get('/mudeo/next', [AdminMudeo::class, 'next'])->name('mudeo.next');
        Route::post('/mudeo/batches', [AdminMudeo::class, 'send'])->name('mudeo.batches.create');
        Route::delete('/mudeo/batches/latest', [AdminMudeo::class, 'undo'])->name('mudeo.batches.undo');
        Route::post('/mudeo/mark', [AdminMudeo::class, 'mark'])->name('mudeo.mark');
        Route::get('/mudeo/contacts', [AdminMudeo::class, 'contacts'])->name('mudeo.contacts');
        Route::get('/gallery/held', [AdminGallery::class, 'held'])->name('gallery.held');
        Route::post('/gallery/{kind}/{id}', [AdminGallery::class, 'decide'])->whereIn('kind', ['harmony', 'comment'])->name('gallery.decide');
        Route::put('/harmonies/{harmony}/feature', [AdminGallery::class, 'feature'])->middleware('sudo')->name('harmonies.feature');
        Route::post('/gallery/harmony/{harmony}/guides', [AdminGallery::class, 'guide'])->middleware('sudo')->name('gallery.guides.create');
        Route::get('/library/held', [AdminLibrary::class, 'held'])->name('library.held');
        Route::get('/ideas/held', [AdminIdeas::class, 'held'])->name('ideas.held');
        Route::get('/requests', [RequestsApi::class, 'adminIndex'])->name('requests.list');
        Route::patch('/requests/{id}', [RequestsApi::class, 'update'])->name('requests.update');
        Route::get('/choir-voices/held', [ChoirsApi::class, 'held'])->name('choirs.voices.held');
        Route::post('/choir-voices/{voice}/approve', [ChoirsApi::class, 'approve'])->whereUuid('voice')->name('choirs.voices.approve');
        Route::post('/choir-voices/{voice}/remove', [ChoirsApi::class, 'remove'])->whereUuid('voice')->name('choirs.voices.remove');
        Route::put('/coaches/{handle}/feature', [AdminCoachDirectory::class, 'feature'])->middleware('sudo')->name('coaches.feature');
        Route::post('/ideas/{idea}', [AdminIdeas::class, 'decide'])->where('idea', '[A-Za-z0-9]{12}')->name('ideas.decide');
        Route::post('/library/{template}', [AdminLibrary::class, 'decide'])->whereUuid('template')->name('library.decide');
        Route::put('/library/{template}/feature', [AdminLibrary::class, 'feature'])->whereUuid('template')->middleware('sudo')->name('library.feature');
        Route::get('/network', [AdminNetwork::class, 'show'])->name('network.show');
        Route::get('/counts', [CountsController::class, 'show'])->name('counts.show');
        Route::get('/funnel', AdminFunnel::class)->name('funnel.show');
        Route::get('/diagnostics/country', [AdminDiagnostics::class, 'country'])->name('diagnostics.country');
        Route::get('/boards/flags', [AdminBoards::class, 'flags'])->name('boards.flags.list');
        Route::post('/boards/scores/{score}/review', [AdminBoards::class, 'review'])->name('boards.scores.review');
        Route::delete('/boards/scores/{score}', [AdminBoards::class, 'hide'])->middleware('sudo')->name('boards.scores.delete');
        Route::post('/people/{person}/boards/reset', [AdminBoards::class, 'reset'])->middleware('sudo')->name('boards.reset');
        Route::put('/network/{feature}', [AdminNetwork::class, 'update'])->middleware('sudo')->name('network.update');
        Route::get('/support', [AdminSupport::class, 'index'])->name('support.list');
        Route::get('/support/{message}', [AdminSupport::class, 'show'])->name('support.show');
        Route::post('/support/{message}/reply', [AdminSupport::class, 'reply'])->name('support.reply');
        Route::patch('/support/{message}', [AdminSupport::class, 'update'])->name('support.update');
        Route::get('/appeals', [AdminSupport::class, 'appeals'])->name('appeals.list');
        Route::post('/appeals/{notice}', [AdminSupport::class, 'decide'])->middleware('sudo')->name('appeals.decide');
        Route::get('/social', [AdminSocial::class, 'index'])->name('social.list');
        Route::post('/social/harmonies/{harmony}', [AdminSocial::class, 'feature'])->name('social.feature');
        Route::post('/social/{post}/approve', [AdminSocial::class, 'approve'])->name('social.approve');
        Route::post('/social/{post}/cancel', [AdminSocial::class, 'cancel'])->name('social.cancel');
        Route::post('/social/{post}/retry', [AdminSocial::class, 'retry'])->name('social.retry');
        Route::get('/books', [AdminBooks::class, 'show'])->name('books.show');
        Route::post('/books', [AdminBooks::class, 'connect'])->middleware(['sudo', 'throttle:books-connect'])->name('books.connect');
        Route::delete('/books', [AdminBooks::class, 'destroy'])->middleware('sudo')->name('books.delete');
        Route::get('/books/records', [AdminBooks::class, 'records'])->name('books.records.list');
        Route::post('/books/records/{record}/retry', [AdminBooks::class, 'retry'])->name('books.records.retry');
        Route::post('/books/plus-invoices', [AdminBooks::class, 'invoice'])->middleware('sudo')->name('books.plusInvoices.create');
        Route::post('/harmonies/{harmony}/hide', [AdminReports::class, 'hide'])->name('harmonies.hide');
        Route::post('/harmonies/{harmony}/restore', [AdminReports::class, 'restore'])->name('harmonies.restore');
        Route::post('/harmonies/{harmony}/remove', [AdminReports::class, 'remove'])->middleware('sudo')->name('harmonies.remove');

        Route::get('/people', [PeopleController::class, 'index'])->name('people.list');
        Route::get('/people/countries', [PeopleController::class, 'countries'])->name('people.countries');
        Route::get('/people/{person}', [PeopleController::class, 'show'])->name('people.show');
        Route::get('/people/{person}/payment', [PeopleController::class, 'payment'])->name('people.payment');
        Route::middleware('sudo')->group(function (): void {
            Route::post('/people/{person}/suspend', [PeopleController::class, 'suspend'])->name('people.suspend');
            Route::post('/people/{person}/unsuspend', [PeopleController::class, 'unsuspend'])->name('people.unsuspend');
            Route::put('/people/{person}/trust', [PeopleController::class, 'trust'])->name('people.trust.update');
            Route::put('/people/{person}/store-tester', [PeopleController::class, 'storeTester'])->name('people.storeTester.update');
            Route::get('/store-testers', [PeopleController::class, 'storeTesters'])->name('storeTesters.list');
            Route::post('/people/{person}/recover', [PeopleController::class, 'recover'])->name('people.recover');
            Route::post('/people/{person}/refund', [PeopleController::class, 'refund'])->name('people.refund');
        });
    });
});
