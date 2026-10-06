{{-- Settings → LMS. One root element: it sits inside an x-if template. --}}
<form class="settings-stack" @submit.prevent="saveGroup('lms')" autocomplete="off">

  <section class="panel">
    <div class="settings-panel-head">
      <h3>Selling courses</h3>
      <p>How prices are written, and what happens when someone buys a paid course.</p>
    </div>
    <div class="settings-grid settings-grid-pairs p-5">
      <div class="field">
        <label for="lms-currency">Currency symbol</label>
        <input id="lms-currency" class="input" maxlength="8" x-model="lms.currency" :disabled="!can.update" placeholder="৳">
        <p class="mt-1.5 text-[11px] text-neutral-500">Printed before every price: ৳, Tk, $, €, ₹ …</p>
        <p class="field-error" x-show="fieldError('currency')" x-text="fieldError('currency')"></p>
      </div>

      <label class="switch-row">
        <input type="checkbox" class="switch" x-model="lms.auto_approve" :disabled="!can.update">
        <span>
          <strong>Open paid courses straight away</strong>
          <span>Off: a paid sign-up waits as <em>Pending</em> under LMS → Enrollments until you check the payment and approve it. On: the course opens the moment the student submits a transaction ID.</span>
        </span>
      </label>

      <div class="field">
        <label for="lms-methods">Payment methods <span class="font-normal text-neutral-500">— one per line</span></label>
        <textarea id="lms-methods" class="input !min-h-[110px] py-2" x-model="lms.payment_methods" :disabled="!can.update"
                  placeholder="bKash&#10;Nagad&#10;Bank transfer"></textarea>
        <p class="mt-1.5 text-[11px] text-neutral-500">The checkout offers these as choices.</p>
        <p class="field-error" x-show="fieldError('payment_methods')" x-text="fieldError('payment_methods')"></p>
      </div>

      <div class="field">
        <label for="lms-instructions">Payment instructions</label>
        <textarea id="lms-instructions" class="input !min-h-[110px] py-2" x-model="lms.payment_instructions" :disabled="!can.update"
                  placeholder="Send the fee to our bKash merchant number 01XXXXXXXXX…"></textarea>
        <p class="mt-1.5 text-[11px] text-neutral-500">Shown at the top of the checkout: where to send the money.</p>
        <p class="field-error" x-show="fieldError('payment_instructions')" x-text="fieldError('payment_instructions')"></p>
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="settings-panel-head">
      <h3>Lesson videos</h3>
      <p>Videos uploaded in a lesson are stored privately on this site and played only to students who may watch them.</p>
    </div>
    <div class="settings-grid settings-grid-pairs p-5">
      <div class="field">
        <label for="lms-max-video">Largest video upload (MB)</label>
        <input id="lms-max-video" type="number" min="10" max="20480" class="input" x-model.number="lms.max_video_mb" :disabled="!can.update">
        <p class="mt-1.5 text-[11px] text-neutral-500">Videos are sent in small pieces, so PHP's upload limit does not matter — but each one takes this much of your hosting's disk.</p>
        <p class="field-error" x-show="fieldError('max_video_mb')" x-text="fieldError('max_video_mb')"></p>
      </div>
      <div class="role-note">
        <strong>Tip:</strong> export lessons as <strong>MP4 (H.264)</strong> at 720p — it plays in every browser and keeps files small
        (about 300–500 MB per hour). Every view streams from your server, so watch your hosting's bandwidth.
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="settings-panel-head">
      <h3>Students</h3>
      <p>Who may sign up, and what they get along the way.</p>
    </div>
    <div class="settings-grid settings-grid-pairs p-5">
      <label class="switch-row">
        <input type="checkbox" class="switch" x-model="lms.allow_registration" :disabled="!can.update">
        <span>
          <strong>Anyone can create a student account</strong>
          <span>Off: the Register page closes, and only admins can add students (LMS → Enrollments → Enroll a student).</span>
        </span>
      </label>

      <label class="switch-row">
        <input type="checkbox" class="switch" x-model="lms.sequential" :disabled="!can.update">
        <span>
          <strong>Lessons in order</strong>
          <span>A student must mark a lesson complete before the next one opens. Free previews and staff who can edit the course are not held back.</span>
        </span>
      </label>

      <label class="switch-row">
        <input type="checkbox" class="switch" x-model="lms.reviews" :disabled="!can.update">
        <span>
          <strong>Course reviews</strong>
          <span>Students on a course can rate it from one to five stars and leave a comment on its page.</span>
        </span>
      </label>

      <label class="switch-row">
        <input type="checkbox" class="switch" x-model="lms.certificates" :disabled="!can.update">
        <span>
          <strong>Certificates</strong>
          <span>A printable certificate once every lesson of a course is marked complete.</span>
        </span>
      </label>

      <div class="flex flex-col gap-4" x-show="lms.certificates">
        <div class="field">
          <label for="lms-cert-text">Certificate wording</label>
          <input id="lms-cert-text" class="input" maxlength="200" x-model="lms.certificate_text" :disabled="!can.update"
                 placeholder="has successfully completed the course">
          <p class="mt-1.5 text-[11px] text-neutral-500">Printed between the student's name and the course title.</p>
        </div>
        <div class="field">
          <label for="lms-cert-signer">Signed by <span class="font-normal text-neutral-500">— optional</span></label>
          <input id="lms-cert-signer" class="input" maxlength="120" x-model="lms.certificate_signer" :disabled="!can.update"
                 placeholder="Blank: the course's instructor">
        </div>
      </div>
    </div>
  </section>

  <div class="settings-savebar" x-show="can.update">
    <span class="counter" x-show="dirty">Unsaved changes</span>
    <button type="button" class="btn btn-ghost btn-sm" x-show="dirty" @click="discard()">Undo</button>
    <a class="btn btn-secondary" href="/courses" target="_blank" rel="noopener">View courses ↗</a>
    <button type="submit" class="btn btn-primary ml-auto" :disabled="saving || !dirty"
            x-text="saving ? 'Saving…' : 'Save LMS settings'"></button>
  </div>
</form>
