<?php
// הקובץ הזה הוא חלק מ‑Moodle - http://moodle.org/
//
// Moodle הוא תוכנה חופשית: באפשרותך להפיץ אותו ולשנות אותו בתנאים של
// רישיון GPL כפי שפורסם על‑ידי Free Software Foundation,
// או (לבחירתך) בכל רישיון מאוחר יותר.
//
// Moodle מופץ בתקווה שיועיל,
// אך ללא אחריות מכל סוג — אפילו בלי האחריות המובטחת
// לסחירות או התאמה למטרה מסוימת. ראה את
// רישיון GPL למידע נוסף.
//
// היית אמור לקבל עותק של רישיון GPL עם Moodle.
// אם לא — ראה <http://www.gnu.org/licenses/>.

/**
 * מחרוזות שפה באנגלית לתוסף Annual Training Forecast
 *
 * @package    local_annualtrainingforecast
 * @copyright  2025 Mattan Dor (CentricApp)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'תחזית שנתי להכשרה';

// ניווט
$string['ganttview'] = 'תצוגת גאנט';
$string['managecourses'] = 'ניהול קורסים';
$string['reports'] = 'דוחות';

// ניהול קורסים
$string['addparentcourse'] = 'הוסף קורס‑אב';
$string['addcourseinstance'] = 'הוסף מופע קורס';
$string['editcourse'] = 'ערוך קורס';
$string['deletecourse'] = 'מחק קורס';
$string['coursename'] = 'שם הקורס';
$string['coursedescription'] = 'תיאור הקורס';
$string['courseduration'] = 'משך הקורס (בימים)';
$string['startdate'] = 'תאריך התחלה';
$string['enddate'] = 'תאריך סיום';
$string['status'] = 'סטטוס';
$string['completed'] = 'הושלם';
$string['notcompleted'] = 'לא הושלם';

// מקור הקורס
$string['coursesource'] = 'מקור הקורס';
$string['createnewcourse'] = 'צור קורס חדש';
$string['selectexistingcourse'] = 'בחר קורס קיים';
$string['newcoursedetails'] = 'פרטי הקורס החדש';
$string['existingcoursedetails'] = 'פרטי הקורס הקיים';
$string['selectcourse'] = 'בחר קורס';

// אפשרויות סטטוס
$string['status_upcoming'] = 'מתוכנן';
$string['status_inprogress'] = 'בתהליך';
$string['status_completed'] = 'הושלם';
$string['status_cancelled'] = 'בוטל';

// הודעות
$string['courseadded'] = 'הקורס נוסף בהצלחה';
$string['courseupdated'] = 'הקורס עודכן בהצלחה';
$string['coursedeleted'] = 'הקורס נמחק בהצלחה';
$string['instanceadded'] = 'מופע הקורס נוסף בהצלחה';
$string['instanceupdated'] = 'מופע הקורס עודכן בהצלחה';
$string['instancedeleted'] = 'מופע הקורס נמחק בהצלחה';
$string['nocourses'] = 'לא נמצאו קורסים. הוסף קורס‑אב כדי להתחיל.';
$string['noiterations'] = 'לא נמצאו מופעי קורס עבור הקורס הזה.';
$string['cannotdeletecourse'] = 'לא ניתן למחוק קורס: קיימים לו מופעי קורס. יש למחוק את כל המופעים קודם.';

// הודעות אישור
$string['confirmdelete'] = 'האם אתה בטוח שברצונך למחוק';
$string['deletecoursewarning'] = 'זה יסיר את הקורס ממערכת תחזית השנתי להכשרה, אך קורס Moodle המשויך יישאר ללא שינוי.';
$string['deleteiterationwarning'] = 'זה יסיר את מופע הקורס ממערכת תחזית השנתי להכשרה, אך קורס Moodle המשויך יישאר ללא שינוי.';

// מחרוזות חסרות שמשמשות בקוד
$string['parentcourse'] = 'קורס‑אב';
$string['yearview'] = 'תצוגת שנה';
$string['halfyearview'] = 'תצוגת חצי שנה';
$string['quarterlyview'] = 'תצוגת רבעון';
$string['updatefailed'] = 'העדכון נכשל';
$string['enddatebeforestartdate'] = 'תאריך הסיום לא יכול להיות לפני תאריך ההתחלה';
$string['exportexcel'] = 'ייצא ל‑Excel';
$string['exportpdf'] = 'ייצא ל‑PDF';
$string['statussummary'] = 'תקציר סטטוס';
$string['count'] = 'כמות';
$string['completionsummary'] = 'תקציר סיום';
$string['monthlydistribution'] = 'התפלגות חודשית';
$string['month'] = 'חודש';
