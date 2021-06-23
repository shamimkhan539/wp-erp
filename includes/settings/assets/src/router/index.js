import Vue from 'vue';
import Router from 'vue-router';
import GeneralSettings from '../components/general/GeneralSettings.vue';

// HRM Components
import HRWorkDays from '../components/hr/workdays/HRWorkDays.vue';
import HRLeave from '../components/hr/leave/HRLeave.vue';
import HRLeaveYears from '../components/hr/leave-years/HRLeaveYears.vue';
import HRMiscellaneous from '../components/hr/miscellaneous/HRMiscellaneous.vue';

// AC Components
import AcCustomer from '../components/act/customer/AcCustomer.vue';
import AcCurrency from '../components/act/currency/AcCurrency.vue';
import AcFinancialYears from '../components/act/financial-year/AcFinancialYears.vue';

// CRM Components
import CrmContacts from '../components/crm/contacts/CrmContacts.vue';
import CrmSubscription from '../components/crm/subscription/CrmSubscription.vue';
import CrmTemplate from '../components/crm/templates/CrmTemplate.vue';

Vue.use(Router);

export default new Router({
    linkActiveClass: 'router-link-active',
    routes: settings.hooks.applyFilters('erp_settings_admin_routes', [
        {
            path     : '/',
            component: GeneralSettings,
            children : [
                {
                    path     : 'general',
                    name     : 'GeneralSettings',
                    component: GeneralSettings,
                    alias    : '/'
                }
            ]
        },

        {
            path     : '/erp-hr',
            name     : 'HR',
            component: {
                render(c) {
                    return c('router-view');
                }
            },
            children: [
                {
                    path     : 'workdays',
                    name     : 'HRWorkDays',
                    component: HRWorkDays,
                    alias    : '/erp-hr'
                },
                {
                    path     : 'leave',
                    name     : 'HRLeave',
                    component: HRLeave
                },
                {
                    path     : 'financial',
                    name     : 'HRLeaveYears',
                    component: HRLeaveYears
                },
                {
                    path     : 'miscellaneous',
                    name     : 'HRMiscellaneous',
                    component: HRMiscellaneous
                }
            ]
        },

        {
            path     : '/erp-ac',
            name     : 'Ac',
            component: {
                render(c) {
                    return c('router-view');
                }
            },
            children: [
                {
                    path     : 'customers',
                    name     : 'AcCustomer',
                    component: AcCustomer,
                    alias    : '/erp-ac'
                },
                {
                    path     : 'currency_option',
                    name     : 'AcCurrency',
                    component: AcCurrency
                },
                {
                    path     : 'opening_balance',
                    name     : 'AcFinancialYears',
                    component: AcFinancialYears
                }
            ]
        },

        {
            path     : '/erp-crm',
            name     : 'Crm',
            component: {
                render(c) {
                    return c('router-view');
                }
            },
            children: [
                {
                    path     : 'contacts',
                    name     : 'CrmContacts',
                    component: CrmContacts,
                    alias    : '/erp-crm'
                },
                {
                    path     : 'subscription',
                    name     : 'CrmSubscription',
                    component: CrmSubscription
                },
                {
                    path     : 'templates',
                    name     : 'CrmTemplate',
                    component: CrmTemplate
                },
            ]
        }
    ])
});