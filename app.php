<?php
session_start();
require_once __DIR__ . '/db.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // require logged-in user
  if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
  }

  $student_id = (int) $_SESSION['user_id'];
  $fullname = trim($_POST['fullname'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $institution = trim($_POST['institution'] ?? '');
  $field = trim($_POST['field'] ?? '');
  $gpa = trim($_POST['gpa'] ?? '');
  $school = trim($_POST['school'] ?? '');
  $scholarship = trim($_POST['scholarship'] ?? '');
  $statement = trim($_POST['statement'] ?? '');

  // Basic validation
  if (!$fullname || !$email || !$phone || !$institution || !$field || !$gpa || !$school || !$scholarship || !$statement) {
    header('Location: app.php?status=error');
    exit;
  }

  // Handle transcript upload
  $uploadDir = __DIR__ . '/uploads/transcripts';
  if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

  $transcriptPath = '';
  if (!empty($_FILES['transcript']) && $_FILES['transcript']['error'] === UPLOAD_ERR_OK) {
    $fileTmp = $_FILES['transcript']['tmp_name'];
    $fileName = basename($_FILES['transcript']['name']);
    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
    $allowed = ['pdf','jpg','jpeg','png','gif'];
    if (!in_array(strtolower($ext), $allowed)) {
      header('Location: app.php?status=error');
      exit;
    }
    $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
    $newName = $safeName . '-' . time() . '.' . $ext;
    $dest = $uploadDir . '/' . $newName;
    if (!move_uploaded_file($fileTmp, $dest)) {
      header('Location: app.php?status=error');
      exit;
    }
    // store relative path
    $transcriptPath = 'uploads/transcripts/' . $newName;
  } else {
    // required file missing
    header('Location: app.php?status=error');
    exit;
  }

  try {
    $pdo = get_db_pdo();
    $stmt = $pdo->prepare('INSERT INTO scholarship_applications (student_id, fullname, email, phone, institution, field, gpa, school, scholarship, statement, transcript) VALUES (:student_id, :fullname, :email, :phone, :institution, :field, :gpa, :school, :scholarship, :statement, :transcript)');
    $stmt->execute([
      ':student_id' => $student_id,
      ':fullname' => $fullname,
      ':email' => $email,
      ':phone' => $phone,
      ':institution' => $institution,
      ':field' => $field,
      ':gpa' => $gpa,
      ':school' => $school,
      ':scholarship' => $scholarship,
      ':statement' => $statement,
      ':transcript' => $transcriptPath,
    ]);
    header('Location: app.php?status=success');
    exit;
  } catch (Exception $e) {
    // log the error server-side for debugging
    error_log('Application submit error: ' . $e->getMessage());
    header('Location: app.php?status=error');
    exit;
  }
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Scholarship Application</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; font-family:"Poppins", sans-serif; }
    body { background-color: #f4f8ff; color:#333; display:flex; justify-content:center; padding:40px 20px; }
    .container { background:#fff; width:100%; max-width:700px; border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,0.1); overflow:hidden; }
    header { background: linear-gradient(135deg, #007bff, #1e90ff); color:white; text-align:center; padding:25px; }
    header h1 { font-size:1.8rem; font-weight:600; }
    form { padding:30px; }
    h2.section-title { font-size:1.1rem; color:#0056b3; margin-bottom:10px; border-left:4px solid #007bff; padding-left:8px; }
    .form-group { margin-bottom:18px; }
    label { display:block; font-weight:500; margin-bottom:6px; color:#444; }
    input, select, textarea { width:100%; padding:10px 12px; border:1px solid #c9defd; border-radius:8px; font-size:0.95rem; outline:none; transition:border 0.3s; }
    input:focus, select:focus, textarea:focus { border-color:#007bff; box-shadow:0 0 4px rgba(0,123,255,0.3); }
    textarea { resize:none; height:100px; }
    .file-upload { border:2px dashed #a5c8ff; padding:20px; border-radius:10px; text-align:center; background-color:#f9fcff; margin-bottom:20px; transition:border-color 0.3s; }
    .file-upload:hover { border-color:#007bff; }
    .file-upload input[type="file"] { display:none; }
    .file-label { display:inline-block; padding:10px 18px; background-color:#007bff; color:white; border-radius:6px; cursor:pointer; transition: background-color 0.3s; margin-top:10px; }
    .file-label:hover { background-color:#0056b3; }
    button { width:100%; padding:12px; background-color:#007bff; color:white; border:none; border-radius:8px; font-size:1rem; cursor:pointer; transition: background 0.3s; }
    button:hover { background-color:#0056b3; }
    footer { text-align:center; padding:15px; background-color:#f1f6ff; font-size:0.9rem; color:#666; }
    @media (max-width:600px) { header h1 { font-size:1.4rem; } }
    /* Modal styles for status message */
    .modal { position:fixed; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.45); z-index:1200; }
    .modal[hidden] { display:none; }
    .modal-content { position:relative; background:#fff; padding:22px 22px 18px 22px; border-radius:10px; width:90%; max-width:520px; box-shadow:0 10px 40px rgba(0,0,0,0.2); text-align:center; }
    .modal-content p { margin:0; font-size:1rem; color:#222; }
    /* place close button relative to the modal box */
    /* no visible close button; modal closes on any click */
    /* When modal is open, blur the page content behind it */
    body.modal-open .container { filter: blur(5px) saturate(0.98); transition: filter 180ms ease-in-out; }
    /* Ensure modal stays sharp above the blurred content */
    body.modal-open .modal { backdrop-filter: none; }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <h1>Scholarship Application Form</h1>
    </header>

    <!-- Status Modal (hidden by default) -->
    <div id="status-modal" class="modal" hidden role="dialog" aria-modal="true" aria-labelledby="status-text">
      <div class="modal-content">
        <p id="status-text"></p>
      </div>
    </div>

    <form enctype="multipart/form-data" action="app.php" method="POST">
      <!-- Personal Information -->
      <h2 class="section-title">Personal Information</h2>
      <div class="form-group">
        <label for="fullname">Full Name</label>
        <input type="text" id="fullname" name="fullname" placeholder="Enter your full name" required>
      </div>
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" placeholder="Enter your email address" required>
      </div>
      <div class="form-group">
        <label for="phone">Phone Number</label>
        <input type="tel" id="phone" name="phone" placeholder="Enter your phone number" required>
      </div>

      <!-- Academic Information -->
      <h2 class="section-title">Academic Information</h2>
      <div class="form-group">
        <label for="institution">Name of Institution</label>
        <input type="text" id="institution" name="institution" placeholder="Your school or university" required>
      </div>
      <div class="form-group">
        <label for="field">Field of Study</label>
        <input type="text" id="field" name="field" placeholder="E.g. Engineering, Science..." required>
      </div>
      <div class="form-group">
        <label for="gpa">Current GPA or Average Grade</label>
        <input type="text" id="gpa" name="gpa" placeholder="e.g. 3.8 / 4.0" required>
      </div>

      <!-- Scholarship Selection -->
      <h2 class="section-title">Scholarship Applying For</h2>
      <div class="form-group">
        <label for="school">Select School</label>
        <select id="school" name="school" required>
          <option value="">-- Choose a School --</option>
          <option value="engineering">School of Engineering</option>
          <option value="science">School of Science</option>
          <option value="business">School of Business</option>
          <option value="humanities">School of Humanities</option>
          <option value="computing">School of Computing & IT</option>
        </select>
      </div>
      <div class="form-group">
        <label for="scholarship">Select Scholarship</label>
        <select id="scholarship" name="scholarship" required>
          <option value="">-- Select a School First --</option>
        </select>
      </div>

      <!-- Personal Statement -->
      <h2 class="section-title">Personal Statement</h2>
      <div class="form-group">
        <label for="statement">Tell us why you deserve this scholarship</label>
        <textarea id="statement" name="statement" placeholder="Explain your goals, achievements, and motivation..." required></textarea>
      </div>

      <!-- Upload Transcript -->
      <h2 class="section-title">Upload Transcript</h2>
      <div class="file-upload">
        <p>Please upload a clear image or PDF copy of your academic transcript.</p>
        <label class="file-label" for="transcript">Choose File</label>
        <input type="file" id="transcript" name="transcript" accept="image/*,application/pdf" required>
      </div>

      <button type="submit">Submit Application</button>
    </form>

    <footer>
      © 2025 Scholarship Portal — All Rights Reserved.
    </footer>
  </div>

  <script>
    const scholarshipsBySchool = {
      engineering: [
        { value:'innovation-tech', text:'Innovation & Technology Scholarship' },
        { value:'future-builders', text:'Future Builders Award' }
      ],
      science: [
        { value:'blue-future', text:'Blue Future Foundation Scholarship' },
        { value:'stem-innovators', text:'STEM Innovators Grant' }
      ],
      business: [
        { value:'entrepreneurial-minds', text:'Entrepreneurial Minds Scholarship' },
        { value:'future-leaders', text:'Future Leaders in Business Award' }
      ],
      humanities: [
        { value:'global-leaders', text:'Global Leaders Scholarship' },
        { value:'creative-minds', text:'Creative Minds Award' }
      ],
      computing: [
        { value:'tech-innovators', text:'Tech Innovators Scholarship' },
        { value:'ai-excellence', text:'AI Excellence Award' }
      ]
    };

    const schoolSelect = document.getElementById('school');
    const scholarshipSelect = document.getElementById('scholarship');
    const transcriptInput = document.getElementById('transcript');
    const fileLabel = document.querySelector('.file-label');
    const form = document.querySelector('form');

    schoolSelect.addEventListener('change', () => {
      const selectedSchool = schoolSelect.value;
      scholarshipSelect.innerHTML = '';
      if (selectedSchool && scholarshipsBySchool[selectedSchool]) {
        scholarshipsBySchool[selectedSchool].forEach(s => {
          const option = document.createElement('option');
          option.value = s.value;
          option.text = s.text;
          scholarshipSelect.appendChild(option);
        });
      } else {
        const option = document.createElement('option');
        option.value = '';
        option.text = '-- Select a School First --';
        scholarshipSelect.appendChild(option);
      }
    });

    // Dynamic file label
    transcriptInput.addEventListener('change', () => {
      if(transcriptInput.files.length>0){
        fileLabel.textContent = transcriptInput.files[0].name;
      } else {
        fileLabel.textContent = "Choose File";
      }
    });

    // Validate file type before submission
    form.addEventListener('submit', (e) => {
      const file = transcriptInput.files[0];
      if(!file) return;
      const allowedTypes = ['application/pdf','image/jpeg','image/png','image/jpg','image/gif'];
      if(!allowedTypes.includes(file.type)){
        alert('Invalid file type! Please upload a PDF or an image.');
        e.preventDefault();
      }
    });
    
    // Show status modal if ?status=success or ?status=error is present
    (function(){
      const params = new URLSearchParams(window.location.search);
      const status = params.get('status');
      if (!status) return;
      const modal = document.getElementById('status-modal');
      const statusText = document.getElementById('status-text');
      if (!modal || !statusText) return;
      if (status === 'success') {
        statusText.textContent = 'Your application has been submitted and is awaiting approval.';
      } else {
        statusText.textContent = 'There was an error submitting your application. Please try again.';
      }
      modal.removeAttribute('hidden');

      // close handler: any click on the page closes the modal
      document.addEventListener('click', closeModal);

      function closeModal(){
        modal.setAttribute('hidden','');
        try { history.replaceState(null, '', window.location.pathname); } catch(e) {}
        try { document.removeEventListener('click', closeModal); } catch(e) {}
      }
    })();
  </script>
</body>
</html>
