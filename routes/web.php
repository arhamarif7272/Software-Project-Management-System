<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MainController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication (Commented Out to bypass login/register)
|--------------------------------------------------------------------------
*/
// Route::middleware('guest')->group(function () {
//     Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
//     Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
//     Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
//     Route::post('/register', [AuthController::class, 'register'])->name('register.store');
// });

// Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Application (all six modules live in MainController)
|--------------------------------------------------------------------------
*/
// Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route('projects.index'));

    Route::post('/status-date', [MainController::class, 'setStatusDate'])->name('status-date.set');

    // ---- Module 1: Initiation & Integration ----
    Route::get('/projects', [MainController::class, 'projectsIndex'])->name('projects.index');
    Route::get('/projects/create', [MainController::class, 'projectCreate'])->name('projects.create');
    Route::post('/projects', [MainController::class, 'projectStore'])->name('projects.store');
    Route::get('/projects/{project}', [MainController::class, 'projectShow'])->name('projects.show');
    Route::put('/projects/{project}', [MainController::class, 'projectUpdate'])->name('projects.update');
    Route::delete('/projects/{project}', [MainController::class, 'projectDestroy'])->name('projects.destroy');
    Route::post('/projects/{project}/charter/approve', [MainController::class, 'charterApprove'])->name('charter.approve');
    Route::put('/projects/{project}/plan', [MainController::class, 'planUpdate'])->name('plan.update');
    Route::post('/projects/{project}/status', [MainController::class, 'statusUpdate'])->name('projects.status');
    Route::post('/projects/{project}/close', [MainController::class, 'projectClose'])->name('projects.close');

    Route::post('/projects/{project}/stakeholders', [MainController::class, 'stakeholderStore'])->name('stakeholders.store');
    Route::delete('/stakeholders/{stakeholder}', [MainController::class, 'stakeholderDestroy'])->name('stakeholders.destroy');

    Route::post('/projects/{project}/team', [MainController::class, 'teamStore'])->name('team.store');
    Route::delete('/team/{member}', [MainController::class, 'teamDestroy'])->name('team.destroy');

    Route::post('/projects/{project}/milestones', [MainController::class, 'milestoneStore'])->name('milestones.store');
    Route::put('/milestones/{milestone}', [MainController::class, 'milestoneUpdate'])->name('milestones.update');
    Route::delete('/milestones/{milestone}', [MainController::class, 'milestoneDestroy'])->name('milestones.destroy');

    // ---- Module 2: Scope & WBS ----
    Route::get('/projects/{project}/requirements', [MainController::class, 'requirementsIndex'])->name('requirements.index');
    Route::post('/projects/{project}/requirements', [MainController::class, 'requirementStore'])->name('requirements.store');
    Route::put('/requirements/{requirement}', [MainController::class, 'requirementUpdate'])->name('requirements.update');
    Route::post('/requirements/{requirement}/approve', [MainController::class, 'requirementApprove'])->name('requirements.approve');
    Route::post('/requirements/{requirement}/status', [MainController::class, 'requirementStatus'])->name('requirements.status');
    Route::delete('/requirements/{requirement}', [MainController::class, 'requirementDestroy'])->name('requirements.destroy');

    Route::get('/projects/{project}/wbs', [MainController::class, 'wbsIndex'])->name('wbs.index');
    Route::post('/projects/{project}/wbs', [MainController::class, 'wbsStore'])->name('wbs.store');
    Route::post('/projects/{project}/wbs/generate', [MainController::class, 'wbsGenerate'])->name('wbs.generate');
    Route::delete('/wbs/{item}', [MainController::class, 'wbsDestroy'])->name('wbs.destroy');

    Route::get('/projects/{project}/change-requests', [MainController::class, 'changeRequestsIndex'])->name('change-requests.index');
    Route::post('/projects/{project}/change-requests', [MainController::class, 'changeRequestStore'])->name('change-requests.store');
    Route::post('/change-requests/{changeRequest}/approve', [MainController::class, 'changeRequestApprove'])->name('change-requests.approve');
    Route::post('/change-requests/{changeRequest}/reject', [MainController::class, 'changeRequestReject'])->name('change-requests.reject');
    Route::delete('/change-requests/{changeRequest}', [MainController::class, 'changeRequestDestroy'])->name('change-requests.destroy');

    // ---- Module 3: Schedule & Resources ----
    Route::get('/projects/{project}/activities', [MainController::class, 'activitiesIndex'])->name('activities.index');
    Route::post('/projects/{project}/activities', [MainController::class, 'activityStore'])->name('activities.store');
    Route::put('/activities/{activity}', [MainController::class, 'activityUpdate'])->name('activities.update');
    Route::delete('/activities/{activity}', [MainController::class, 'activityDestroy'])->name('activities.destroy');
    Route::post('/activities/{activity}/dependencies', [MainController::class, 'dependencyStore'])->name('dependencies.store');
    Route::delete('/dependencies/{dependency}', [MainController::class, 'dependencyDestroy'])->name('dependencies.destroy');

    Route::get('/projects/{project}/schedule', [MainController::class, 'scheduleGantt'])->name('schedule.gantt');
    Route::get('/projects/{project}/schedule.json', [MainController::class, 'scheduleJson'])->name('schedule.json');

    // ---- Module 4: Cost & EVM ----
    Route::get('/projects/{project}/costs', [MainController::class, 'costsIndex'])->name('costs.index');
    Route::post('/projects/{project}/costs', [MainController::class, 'costStore'])->name('costs.store');
    Route::delete('/costs/{entry}', [MainController::class, 'costDestroy'])->name('costs.destroy');

    Route::get('/projects/{project}/evm', [MainController::class, 'evmIndex'])->name('evm.index');
    Route::post('/projects/{project}/evm/snapshot', [MainController::class, 'evmSnapshot'])->name('evm.snapshot');

    // ---- Module 5: Quality & Risk ----
    Route::get('/projects/{project}/defects', [MainController::class, 'defectsIndex'])->name('defects.index');
    Route::post('/projects/{project}/defects', [MainController::class, 'defectStore'])->name('defects.store');
    Route::put('/defects/{defect}', [MainController::class, 'defectUpdate'])->name('defects.update');
    Route::delete('/defects/{defect}', [MainController::class, 'defectDestroy'])->name('defects.destroy');

    Route::get('/projects/{project}/quality', [MainController::class, 'qualityIndex'])->name('quality.index');
    Route::post('/projects/{project}/checklists', [MainController::class, 'checklistStore'])->name('checklists.store');
    Route::delete('/checklists/{checklist}', [MainController::class, 'checklistDestroy'])->name('checklists.destroy');
    Route::post('/checklists/{checklist}/items', [MainController::class, 'checklistItemStore'])->name('checklist-items.store');
    Route::post('/checklist-items/{item}/inspections', [MainController::class, 'inspectionStore'])->name('inspections.store');

    Route::get('/projects/{project}/risks', [MainController::class, 'risksIndex'])->name('risks.index');
    Route::post('/projects/{project}/risks', [MainController::class, 'riskStore'])->name('risks.store');
    Route::put('/risks/{risk}', [MainController::class, 'riskUpdate'])->name('risks.update');
    Route::delete('/risks/{risk}', [MainController::class, 'riskDestroy'])->name('risks.destroy');

    Route::get('/projects/{project}/pdpc', [MainController::class, 'pdpcIndex'])->name('pdpc.index');
    Route::post('/risks/{risk}/responses', [MainController::class, 'responseStore'])->name('responses.store');
    Route::put('/responses/{response}', [MainController::class, 'responseUpdate'])->name('responses.update');
    Route::delete('/responses/{response}', [MainController::class, 'responseDestroy'])->name('responses.destroy');

    // ---- Module 6: Dashboard & Reporting ----
    Route::get('/projects/{project}/dashboard', [MainController::class, 'dashboard'])->name('dashboard');
    Route::get('/projects/{project}/report', [MainController::class, 'report'])->name('report');
// });