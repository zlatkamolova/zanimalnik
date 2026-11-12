// Entry point for the EasyAdmin backend (added in DashboardController::configureAssets()).
// Separate from the public app.js so the admin doesn't load Turbo or Live Components.
import { Application } from '@hotwired/stimulus';
import SortableListController from './admin/sortable_list_controller.js';
import './admin/admin.css';

const application = Application.start();
application.register('sortable-list', SortableListController);
