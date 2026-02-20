<?php
// get_dynamic_fields.php
$type = $_GET['type'] ?? '';

if ($type === 'student') {
    ?>
    <div class="form-section">
        <h3><i class="fas fa-graduation-cap"></i> Student Information</h3>
        <div class="dynamic-fields">
            <div class="form-grid">
                <div class="form-group">
                    <label for="course">Course/Program</label>
                    <select id="course" name="course">
                        <option value="">Select Course</option>
                        <option value="bsit">BS Information Technology</option>
                        <option value="bscs">BS Computer Science</option>
                        <option value="bsis">BS Information Systems</option>
                        <option value="bsemc">BS Entertainment and Multimedia Computing</option>
                        <option value="bsda">BS Data Analytics</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="year_level">Year Level</label>
                    <select id="year_level" name="year_level">
                        <option value="">Select Year</option>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="section">Section</label>
                    <input type="text" id="section" name="section" placeholder="e.g., A, B, C">
                </div>
            </div>
        </div>
    </div>
    <?php
} elseif ($type === 'faculty') {
    ?>
    <div class="form-section">
        <h3><i class="fas fa-chalkboard-teacher"></i> Faculty Information</h3>
        <div class="dynamic-fields">
            <div class="form-grid">
                <div class="form-group">
                    <label for="department">Department</label>
                    <select id="department" name="department">
                        <option value="">Select Department</option>
                        <option value="ccis">College of Computer and Information Sciences</option>
                        <option value="coe">College of Engineering</option>
                        <option value="cba">College of Business Administration</option>
                        <option value="cas">College of Arts and Sciences</option>
                        <option value="ced">College of Education</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="position">Position</label>
                    <select id="position" name="position">
                        <option value="">Select Position</option>
                        <option value="professor">Professor</option>
                        <option value="associate_professor">Associate Professor</option>
                        <option value="assistant_professor">Assistant Professor</option>
                        <option value="instructor">Instructor</option>
                        <option value="lecturer">Lecturer</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <?php
} elseif ($type === 'admin') {
    ?>
    <div class="form-section">
        <h3><i class="fas fa-user-shield"></i> Administrator Information</h3>
        <div class="dynamic-fields">
            <div class="form-grid">
                <div class="form-group">
                    <label for="department">Department/Office</label>
                    <input type="text" id="department" name="department" placeholder="e.g., Registrar's Office, IT Department">
                </div>
                <div class="form-group">
                    <label for="position">Admin Position</label>
                    <select id="position" name="position">
                        <option value="">Select Position</option>
                        <option value="registrar">Registrar</option>
                        <option value="system_admin">System Administrator</option>
                        <option value="director">Director</option>
                        <option value="coordinator">Coordinator</option>
                        <option value="supervisor">Supervisor</option>
                    </select>
                </div>
            </div>
            <div class="alert" style="background: #fef3c7; color: #92400e; margin-top: 15px;">
                <i class="fas fa-info-circle"></i> 
                <strong>Note:</strong> Admin accounts have full access to the system dashboard and management features.
            </div>
        </div>
    </div>
    <?php
} else {
    echo '<div class="alert">Please select a user type first.</div>';
}
?>