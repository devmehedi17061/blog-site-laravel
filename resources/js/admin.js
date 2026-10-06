import Alpine from 'alpinejs'

import authStore from './admin/stores/auth.js'
import uiStore from './admin/stores/ui.js'
import pickerStore from './admin/stores/picker.js'
import dialogStore from './admin/stores/dialog.js'

import dashboard from './admin/screens/dashboard.js'
import posts from './admin/screens/posts.js'
import postEditor from './admin/screens/post-editor.js'
import courses from './admin/screens/courses.js'
import courseEditor from './admin/screens/course-editor.js'
import enrollments from './admin/screens/enrollments.js'
import students from './admin/screens/students.js'
import payments from './admin/screens/payments.js'
import media from './admin/screens/media.js'
import mediaPicker from './admin/screens/media-picker.js'
import pages from './admin/screens/pages.js'
import pageEditor from './admin/screens/page-editor.js'
import sectionLibrary from './admin/screens/section-library.js'
import forms from './admin/screens/forms.js'
import formBuilder from './admin/screens/form-builder.js'
import inbox from './admin/screens/inbox.js'
import menu from './admin/screens/menu.js'
import categories from './admin/screens/categories.js'
import users from './admin/screens/users.js'
import roles from './admin/screens/roles.js'
import settings from './admin/screens/settings.js'
import security from './admin/screens/security.js'
import account from './admin/screens/account.js'
import trash from './admin/screens/trash.js'
import headerFooter from './admin/screens/header-footer.js'
import { fieldEditor } from './admin/lib/sections/fieldEditor.js'
import { codeEditor } from './admin/lib/codeEditor.js'

// x-code: the HTML and CSS boxes become code editors (lib/codeEditor.js).
Alpine.plugin(codeEditor)

Alpine.store('auth', authStore)
Alpine.store('ui', uiStore)
Alpine.store('picker', pickerStore)
Alpine.store('dialog', dialogStore)

Alpine.data('dashboard', dashboard)
Alpine.data('posts', posts)
Alpine.data('postEditor', postEditor)
Alpine.data('courses', courses)
Alpine.data('courseEditor', courseEditor)
Alpine.data('enrollments', enrollments)
Alpine.data('students', students)
Alpine.data('payments', payments)
Alpine.data('media', media)
Alpine.data('mediaPicker', mediaPicker)
Alpine.data('pages', pages)
Alpine.data('pageEditor', pageEditor)
Alpine.data('sectionLibrary', sectionLibrary)
Alpine.data('forms', forms)
Alpine.data('formBuilder', formBuilder)
Alpine.data('inbox', inbox)
Alpine.data('menu', menu)
Alpine.data('categories', categories)
Alpine.data('users', users)
Alpine.data('roles', roles)
Alpine.data('settings', settings)
Alpine.data('security', security)
Alpine.data('account', account)
Alpine.data('trash', trash)
Alpine.data('headerFooter', headerFooter)

// Not a screen: one rich text field, mounted wherever a section has one.
Alpine.data('fieldEditor', fieldEditor)

window.Alpine = Alpine
Alpine.start()

Alpine.store('auth').init()
Alpine.store('ui').init()
