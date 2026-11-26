// Entry point for the EasyAdmin backend (added in DashboardController::configureAssets()).
// Separate from the public app.js so the admin doesn't load Turbo or Live Components.
import { Application } from '@hotwired/stimulus';
import SortableListController from './admin/sortable_list_controller.js';
// Same CSRF double-submit as the public site. Without it, a session that already used it there
// (login or registration on the site) has every admin form refused as "Невалиден CSRF токен".
import './controllers/csrf_protection_controller.js';
import './admin/admin.css';

const application = Application.start();
application.register('sortable-list', SortableListController);
