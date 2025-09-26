<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\QuestionsoptionsController;
use App\Http\Controllers\SurveyQuestionsController;
use App\Http\Controllers\TypeQuestionController;
use App\Http\Controllers\SurveyAnswersController;
use App\Http\Controllers\TypeinfoController;
use App\Http\Controllers\AssignmentTypeController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConditionsController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\NotificationSurvaysController;
use App\Http\Controllers\TemporarySurveyController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\AdminCleanupController;
use App\Http\Controllers\ManualSurveyResponseController;
use App\Http\Controllers\SurveyEmailController;
use App\Http\Controllers\SurveyRespondentController;
use App\Http\Controllers\ContactInfoController;



Route::get('/storage/images/{filename}', [FileController::class, 'show']);


Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::post('newusers/store', [UserController::class, 'store']);

// Contact information endpoint (public)
Route::get('contact-info', [ContactInfoController::class, 'getContactInfo']);

// Rutas temporales para testing de grupos (sin autenticación)
Route::prefix('groups-test')->controller(GroupController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store'); // Crear grupo
    Route::post('/add-user', 'addUser');
    Route::post('/add-users', 'addUsers');
    Route::post('/users', 'addUser'); // Agregar usuario (ruta general)
    Route::put('/update/{id}', 'update'); // Actualizar grupo
    Route::put('/{id}', 'update'); // Actualizar grupo (alternativa)
    Route::get('/{id}/users', 'getGroupUsers');
    Route::put('/{groupId}/users/{userId}', 'updateUser');
    Route::delete('/{groupId}/users/{userId}', 'deleteUser');
    Route::delete('/{id}', 'destroy'); // Eliminar grupo
    Route::post('/{id}/users', 'addUserToGroup'); // Agregar usuario a grupo específico
    Route::get('/surveys-list', [SurveyController::class, 'list']);
});

// Ruta temporal para testing de notificaciones (sin autenticación)
Route::post('notification-test/store', [NotificationSurvaysController::class, 'store']);

// Rutas públicas para acceso a encuestas por correo (sin autenticación)
Route::prefix('survey-email')->controller(SurveyEmailController::class)->group(function () {
    Route::post('/validate-access', 'validateAccess')->name('survey.email.validate');
    Route::post('/submit-response', 'submitSurveyResponse')->name('survey.email.submit');
    Route::post('/check-status', 'checkResponseStatus')->name('survey.email.status');
});

// Rutas públicas para respuestas manuales con validación de token
Route::prefix('manual-survey')->controller(ManualSurveyResponseController::class)->group(function () {
    Route::post('/validate-access', 'validateEmailSurveyAccess')->name('manual.survey.validate');
    Route::post('/submit-with-token', 'storeWithTokenValidation')->name('manual.survey.submit.token');
});

// Ruta pública para obtener detalles de encuesta sin autenticación (para respuestas por email)
Route::get('surveys/{id}/public-details', [SurveyController::class, 'getPublicSurveyDetails'])->name('surveys.public.details');

// Rutas públicas para envío de respuestas sin autenticación
Route::post('public/survey-responses', [ManualSurveyResponseController::class, 'store'])->name('public.survey.responses.store');
Route::post('public/manual-survey-responses', [ManualSurveyResponseController::class, 'store'])->name('public.manual.survey.responses.store');


// Temporary test without auth - DISABLED for security
// Route::get('/surveys/', [SurveyController::class, 'index'])->name('surveys.index.test');

Route::middleware(['debug.auth', 'auth:sanctum'])->group(function () {
    
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('users.index');
        Route::post('/store', [UserController::class, 'store'])->name('users.createUser');
        Route::get('/{user}', [UserController::class, 'show'])->name('users.show');
        Route::put('/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        
    });
    Route::get('/roleandusers', [UserController::class, 'getUsersWithRoles'])->name('users.getUsersWithRoles');
    
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/getTokenByEmail', [AuthController::class, 'getTokenByEmail']);
    
    //obtener usuario actual con roles y permisos
    Route::get('/current-user', [AuthController::class, 'getCurrentUser']);


    //asigna los roles  los usuarios //tabla model_has_role
    Route::post('/assign-role', [RoleController::class, 'assignRole']);
    
    //reasigna rol a usuario existente (para edición)
    Route::post('/reassign-role', [RoleController::class, 'reassignRole']);
    
    //asigna los permisos a el rol 
    Route::post('/assign-permission', [RoleController::class, 'assignPermissionsToRole']);
    
    //crea los roles
    Route::post('/create-rol', [RoleController::class, 'createRoles']);
    
    //obtener los permisos del usuario
    Route::get('/UserPermissions', [RoleController::class, 'getUserPermissions']);
    
    //Agregar permisos personales
    Route::get('/PermissionsUserU', [RoleController::class, 'updateUserPermissions']);

     //retorna todos los roles creados
    Route::get('/getAllRoles', [RoleController::class, 'getAllRoles']);

    //Consultar por rol que permisos tiene
    Route::get('/getUserRoles', [RoleController::class, 'getUserRolesAndPermissions']);
    
    
   
    Route::prefix('surveys')->controller(SurveyController::class)->group(function () {
       
        //funcion que optiene todas las encuestas y sus relaciones 
        Route::get('/all/details', 'getAllSurveyDetails')->name('surveys.getAllSurveyDetails');
       //!ruta prueba
       // Route::get('/pin', 'pon')->name('surveys.pon');
      
        Route::get('/', 'index')->name('surveys.index');
        // obtener lista de encuestas para envío masivo
        Route::get('/list', 'list')->name('surveys.list');
        Route::post('/create', 'create')->name('surveys.create');
        Route::post('/store', 'store')->name('surveys.store');
        Route::get('/{id}', 'show')->name('surveys.show');
        Route::put('/update/{id}', 'update')->name('surveys.update');
        Route::put('/update-publication-status/{id}', 'updatePublicationStatus')->name('surveys.updatePublicationStatus');
        Route::delete('/{id}', 'destroy')->name('surveys.destroy');
        //la encuesta a que seccion pertenece
        Route::get('/{id}/sections', 'showSections')->name('surveys.showSections');
         //la encuesta que preguntas contiene
        Route::get('/{id}/questions', 'getSurveyQuestions')->name('surveys.getSurveyQuestions');
        // la encuesta a opcion y pretunta pertenece
        Route::get('/{id}/questionsop', 'getSurveyQuestionsop')->name('surveys.getSurveyQuestionsop');
        // obtener una encuesta con sus secciones
        Route::get('/{id}/sections/details', 'getSurveySections')->name('surveys.getSurveySections');
         // obtener una encuesta completa con sus relaciones
        Route::get('/{id}/details', 'getSurveyDetails')->name('surveys.getSurveyDetails');
        // obtener el conteo de respuestas de una encuesta
        Route::get('/{id}/responses/count', 'getResponsesCount')->name('surveys.getResponsesCount');
        // debug relaciones de una encuesta
        Route::get('/{id}/debug', 'debugSurveyRelations')->name('surveys.debugSurveyRelations');
        // reparar relaciones de una encuesta específica
        Route::post('/{id}/repair', 'repairSurveyQuestions')->name('surveys.repairSurveyQuestions');
        // verificar y reparar relaciones
        Route::get('/repair-relations', 'repairSurveyRelations')->name('surveys.repairSurveyRelations');
        // migrar estados de encuestas basados en fechas
        Route::post('/migrate-states', 'migrateSurveyStates')->name('surveys.migrateSurveyStates');
        // nuevos endpoints para auto-reparación
        Route::get('/{id}/debug-relations', 'debugRelations')->name('surveys.debugRelations');
        Route::post('/{id}/repair-questions', 'repairQuestions')->name('surveys.repairQuestions');
        // endpoint para obtener usuarios que no han respondido (para recordatorios automáticos)
        Route::get('/{id}/non-respondents', 'getNonRespondents')->name('surveys.getNonRespondents');


    });


    Route::prefix('Notification')->controller(NotificationSurvaysController::class)->group(function () {
        Route::get('/all', 'index')->name('Notification.index');
        Route::post('/store', 'store')->name('Notification.store');
        Route::put('/{id}', 'update')->name('Notification.update');
        Route::get('/download', 'download')->name('Notification.download');
        Route::get('/download-respondents-template', 'downloadRespondentsTemplate')->name('Notification.downloadRespondentsTemplate');
        Route::post('/generate-email-links', 'generateSurveyEmailLinks')->name('Notification.generateEmailLinks');
        Route::post('/survey-status', 'getSurveyNotificationStatus')->name('Notification.surveyStatus');
    });


    
    Route::prefix('category')->controller(CategoryController::class)->group(function () {
        Route::get('/', 'index')->name('category.index');
        Route::post('/create', 'create')->name('category.create');
        Route::post('/store', 'store')->name('category.store');
        Route::get('/{id}', 'show')->name('category.show');
        Route::put('/{id}', 'update')->name('category.update');
        Route::delete('/{id}', 'destroy')->name('category.destroy');
        Route::get('/{id}/surveys', 'showSurveys')->name('category.showSurveys');
    });
    
    
    Route::prefix('sections')->controller(SectionController::class)->group(function () {
        Route::get('/', 'index')->name('sections.index');
        Route::post('/create', 'create')->name('sections.create');
        Route::post('/store', 'store')->name('sections.store');
        Route::get('/{id}', 'show')->name('sections.show');
        Route::put('/{id}', 'update')->name('sections.update');
        Route::delete('/{id}', 'destroy')->name('sections.destroy');
        Route::post('/{id}/remove-from-survey', 'removeFromSurvey')->name('sections.removeFromSurvey');
        Route::get('/survey/{id_survey}', 'getSectionsBySurvey')->name('sections.getSectionsBySurvey');

    });
    
    
    Route::prefix('questions')->controller(QuestionController::class)->group(function () {
        Route::get('/', 'index')->name('questions.index');
        Route::post('/store', 'store')->name('questions.store');
        Route::get('/{id}', 'show')->name('questions.show');
        Route::put('/{id}', 'update')->name('questions.update');
        Route::delete('/{id}', 'destroy')->name('questions.destroy');
        Route::get('/{id}/details', 'getQuestionDetails')->name('questions.getQuestionDetails');
    });
    
    Route::prefix('typequestions')->controller(TypeQuestionController::class)->group(function () {
        Route::get('/', 'index')->name('typequestions.index');
        Route::post('/create', 'create')->name('typequestions.create');
        Route::post('/store', 'store')->name('typequestions.store');
        Route::get('/{id}', 'show')->name('typequestions.show');
    });
    

    
    Route::prefix('surveyquestion')->controller(SurveyQuestionsController::class)->group(function () {
        Route::get('/', 'index')->name('surveyquestion.index');
        Route::post('/create', 'create')->name('surveyquestion.create');
        Route::post('/store', 'store')->name('surveyquestion.store');
        Route::get('/{id}', 'show')->name('surveyquestion.show');
        Route::put('/{id}', 'update')->name('surveyquestion.update');
        Route::delete('/{id}', 'destroy')->name('surveyquestion.destroy');
    });
    
    // Alternative route for frontend compatibility
    Route::prefix('survey-questions')->controller(SurveyQuestionsController::class)->group(function () {
        Route::delete('/{id}', 'destroy')->name('survey-questions.destroy');
    });
    
    
    Route::prefix('questionoptions')->controller(QuestionsoptionsController::class)->group(function () {
        Route::get('/', 'index')->name('questionoptions.index');
        Route::post('/create', 'create')->name('questionoptions.create');
        Route::post('/store', 'store')->name('questionoptions.store');
        Route::get('/{id}', 'show')->name('questionoptions.show');
        Route::get('/question/{question_id}', 'getOptionsByQuestion')->name('questionoptions.getByQuestion');
        Route::put('/{id}', 'update')->name('questionoptions.update');
        Route::put('/{id}/text', 'updateOptionText')->name('questionoptions.updateText');
        Route::put('/question/{question_id}', 'updateByQuestion')->name('questionoptions.updateByQuestion');
        Route::delete('/{id}', 'destroy')->name('questionoptions.destroy');
    });
    
    
    Route::prefix('Answers')->controller(SurveyAnswersController::class)->group(function () {
        Route::get('/', 'index')->name('Answers.index');        
        Route::post('/store', 'store')->name('Answers.store');
        Route::get('/{id}', 'show')->name('Answers.show');
        Route::put('/{id}', 'update')->name('Answers.update');
        Route::delete('/{id}', 'destroy')->name('Answers.destroy');
    });

    Route::prefix('Assignmenttype')->controller(AssignmentTypeController::class)->group(function () {
        Route::get('/', 'index')->name('Assignmenttype.index');
        Route::post('/create', 'create')->name('Assignmenttype.create');
        Route::post('/store', 'store')->name('Assignmenttype.store');
        Route::get('/{id}', 'show')->name('Assignmenttype.show');
        Route::put('/{id}', 'update')->name('Assignmenttype.update');
        Route::delete('/{id}', 'destroy')->name('Assignmenttype.destroy');
    });
    
    Route::prefix('Assignment')->controller(AssignmentController::class)->group(function () {
        Route::get('/', 'index')->name('Assignment.index');
        Route::post('/create', 'create')->name('Assignment.create');
        Route::post('/store', 'store')->name('Assignment.store');
        Route::get('/{id}', 'show')->name('Assignment.show');
        Route::put('/{id}', 'update')->name('Assignment.update');
        Route::delete('/{id}', 'destroy')->name('Assignment.destroy');
    });

    Route::prefix('data')->controller(TypeinfoController::class)->group(function () {
        Route::get('/{type}', 'getData')->name('data.getData');
    });
    
    Route::prefix('temporary-surveys')->controller(TemporarySurveyController::class)->group(function () {
        Route::get('/', 'index')->name('temporary-surveys.index');
        Route::post('/', 'store')->name('temporary-surveys.store');
        Route::get('/{id}', 'show')->name('temporary-surveys.show');
        Route::put('/{id}', 'update')->name('temporary-surveys.update');
        Route::delete('/{id}', 'destroy')->name('temporary-surveys.destroy');
        Route::post('/auto-save', 'autoSave')->name('temporary-surveys.auto-save');
        Route::post('/{id}/publish', 'publish')->name('temporary-surveys.publish');
    });
    
    Route::prefix('Conditions')->controller(ConditionsController::class)->group(function () {
        Route::get('/', 'index')->name('Conditions.index');
        // Route::post('/create', 'create')->name('Conditions.create');
        Route::post('/store', 'store')->name('Conditions.store');
        Route::get('/{id}', 'show')->name('Conditions.show');
        Route::put('/{id}', 'update')->name('Conditions.update');
        Route::delete('/{id}', 'destroy')->name('Conditions.destroy');
    });

    Route::prefix('groups')->controller(GroupController::class)->group(function () {
        Route::get('/', 'index')->name('groups.index');
        Route::post('/store', 'store')->name('groups.store');
        Route::get('/{id}', 'show')->name('groups.show');
        Route::delete('/{id}', 'destroy')->name('groups.destroy');
        Route::get('/{id}/users', 'getGroupUsers')->name('groups.getGroupUsers');
        Route::post('/add-user', 'addUser')->name('groups.addUser');
        Route::post('/add-users', 'addUsers')->name('groups.addUsers');
        Route::put('/{groupId}/users/{userId}', 'updateUser')->name('groups.updateUser');
        Route::delete('/{groupId}/users/{userId}', 'deleteUser')->name('groups.deleteUser');
    });

    // Rutas de administración y limpieza
    Route::prefix('admin/cleanup')->controller(AdminCleanupController::class)->group(function () {
        Route::get('/stats', 'getStats')->name('admin.cleanup.stats');
        Route::post('/surveys', 'cleanupSurveys')->name('admin.cleanup.surveys');
        Route::post('/categories', 'cleanupOrphanCategories')->name('admin.cleanup.categories');
        Route::post('/temporaries', 'cleanupTemporarySurveys')->name('admin.cleanup.temporaries');
        Route::post('/specific-surveys', 'deleteSpecificSurveys')->name('admin.cleanup.specific-surveys');
        Route::post('/all', 'cleanupAll')->name('admin.cleanup.all');
    });

    // Rutas para respuestas manuales de encuestas
    Route::prefix('manual-survey-responses')->controller(ManualSurveyResponseController::class)->group(function () {
        Route::post('/', 'store')->name('manual.survey.responses.store');
        Route::get('/survey/{surveyId}', 'getResponsesBySurvey')->name('manual.survey.responses.by.survey');
        Route::get('/', 'getAllResponses')->name('manual.survey.responses.all');
    });

    // Rutas alternativas para compatibilidad con frontend
    Route::post('/survey-responses', [ManualSurveyResponseController::class, 'store'])->name('survey.responses.store');
    Route::get('/surveys/{surveyId}/responses', [ManualSurveyResponseController::class, 'getResponsesBySurvey'])->name('surveys.responses.by.survey');
    
    // Rutas protegidas para gestión de encuestas por correo
    Route::prefix('survey-email')->controller(SurveyEmailController::class)->group(function () {
        Route::post('/generate-link', 'generateSurveyLink')->name('survey.email.generate');
    });

    // Endpoint para envío de recordatorios automáticos
    Route::post('/send-reminder-email', [SurveyEmailController::class, 'sendReminder'])->name('survey.sendReminder');

    // Rutas para gestión de respondientes de encuestas
    Route::prefix('survey-respondents')->controller(SurveyRespondentController::class)->group(function () {
        Route::get('/survey/{surveyId}', 'getBySurvey')->name('survey.respondents.by.survey');
        Route::get('/survey/{surveyId}/stats', 'getStats')->name('survey.respondents.stats');
        Route::post('/mark-responded', 'markAsResponded')->name('survey.respondents.mark.responded');
        Route::put('/{id}', 'update')->name('survey.respondents.update');
        Route::delete('/{id}', 'destroy')->name('survey.respondents.destroy');
    });
    
});
//ruta con autenticacion quemada en env
Route::middleware(['api.key'])->group(function () {
 
});
