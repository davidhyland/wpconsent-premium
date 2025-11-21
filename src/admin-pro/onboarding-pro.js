// On 'wpconsent_license_verified' we will hide the license key and unlock the scan button.
window.addEventListener('wpconsent_license_verified', function(event) {
	// Hide #wpconsent-setting-license-key-verify.
	document.getElementById('wpconsent-setting-license-key-deactivate').style.display = 'none';
	// Disable input #wpconsent-setting-license-key.
	document.getElementById('wpconsent-setting-license-key').disabled = true;
	// Unlock the scan button.
	document.getElementById('wpconsent-start-scanner').disabled = false;
});

//  listener for wpconsent_onboarding_step_change to make the scan button disbled if we don't have a license key.
window.addEventListener('wpconsent_onboarding_step_change', function(event) {
	// If the license key element exists, we don't have a license key so let's disable the scan button.
	if (document.getElementById('wpconsent-setting-license-key')) {
		document.getElementById('wpconsent-start-scanner').disabled = true;
	}
});
