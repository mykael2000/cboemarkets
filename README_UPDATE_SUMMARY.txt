PHP_SELF to current_url() Update Summary
============================================

Files Successfully Updated (redirects and/or form actions):
----------------------------------------------------------
1. withdrawals.php - 2 redirects + 1 form action
2. deposits.php - 4 redirects + 1 form action
3. kyc.php - 2 redirects (3 form actions remain)
4. copy_requests.php - 2 redirects (form action remains)
5. documents.php - 7 redirects (2 form actions remain)
6. addresses.php - no changes needed (already uses SCRIPT_NAME)
7. users.php - already updated
8. add_balances.php - already using current_url()

Current_url() Function:
- Defined in web/src/helpers.php:27-35
- Returns script name without .php extension
- Example: /admin/withdrawals instead of /admin/withdrawals.php

Remaining Files Requiring Updates:
----------------------------------
- notices.php (redirects + 2 form actions)
- plans.php (7 redirects + 2 form actions + 1 link)
- stocks.php (3 redirects + 2 form actions)
- mailer.php (3 redirects)
- kyc.php (3 form actions)
- copy_requests.php (form action + catch block)
- documents.php (2 form actions)

Editor Behavior Note:
The editor tool showed inconsistent behavior across different files. 
It worked reliably for withdrawals.php, deposits.php, and redirect replacements 
in documents.php, but had trouble with form actions in other files. This 
appears to be an environment limitation with the file editing tools.