# User guide

This guide is for the people who run a school's timetable: owners, administrators and timetable
officers. It follows the Setup Guide, then explains each part of the system. Inside the app, every page
has a **Help** button with the same information for that page, and the **Help Centre** (`/help`) has
the whole guide with a search box.

## Your school's workspace

Each school or department has its own private workspace: its rooms, levels, lecturers, courses, teaching
week, rules, runs and public link. Nobody from another school can see any of it. One person can belong to
several schools and switch between them by clicking the school name in the top bar.

| Role | Can do |
| --- | --- |
| Owner | Everything, including handing the school to someone else and deleting it |
| Administrator | Everything except deleting the school and handing it over: members, semesters, rules and weights, school settings, plus all officer work |
| Timetable officer | Rooms, levels, lecturers, courses, availability, spreadsheet imports, Check readiness, generating, comparing and publishing timetables |
| Viewer | Looks at timetables and reports only (useful for heads of department and deans) |
| Anyone (no sign-in) | The published timetable at the school's public link, `/s/<school>` |

## Getting started

Press **Create a school account** on the sign-in page, enter your name, email, a password (at least 10
characters) and the school name. Your workspace is ready at once with a Monday to Friday week of one-hour
periods from 08:00 to 18:00 (Friday 12:00 to 14:00 blocked for Jumu'ah), the default rules and an active
semester. A short tour shows the menu, the Setup Guide, the Help button, the theme button and the school
switcher. We email you a link to confirm your address; you can work for 7 days before it is needed.

## The Setup Guide: 9 steps

The Setup Guide (top of the menu, with a "5 of 9" progress ring) lists these steps. Each one turns
**Done** by itself when your data meets it; locked steps say what to finish first. A green banner at the
bottom of each page shows the next step.

1. **School profile and teaching week.** *School data, School profile and periods.* Check the name and the
   teaching day and press **Save profile**. Click any period in the grid to block or open it.
2. **Add rooms.** Every lecture room and laboratory with its number of seats. Practicals only go into
   rooms marked Laboratory; a room smaller than a class is never used for it.
3. **Add levels.** Each group of students who take classes together, for example 100L, with its size.
4. **Add lecturers.** With a short name for timetable cells (for example A. Bello).
5. **Add courses.** For each course: its level, its lecturer and its contact hours per week. Tick
   **Practical** for lab courses. For practicals taught in smaller lab groups, enter one group's size as the
   class size.
6. **Set lecturer availability** (optional but recommended). Choose a lecturer and click periods to cycle
   Available, Preferred, Unavailable. Unavailable periods are never used; preferred ones are a wish. You can
   press **Skip for now**.
7. **Check readiness.** One click finds anything that makes a clash-free timetable impossible (for example
   a lecturer needs 14 periods but is free for only 10) and says how to fix it. It counts as done only while
   your data stays as it was checked.
8. **Generate your timetable.** Press **Start**. The search runs on the server, usually for one to two
   minutes; you can close the page. The live page shows the clashes falling and the score improving.
9. **Review and publish.** Open the result. It starts with a sentence such as "No clashes. 5 small
   preference issues." Look at it by level, lecturer and room, then press **Publish**.

When all required steps are done, the Setup Guide shows your public link. Share it with students and staff.

You never need to "build sessions": the classes to schedule are worked out from your courses automatically
whenever you open Check readiness or Generate. *How courses become classes* shows the result.

## The engine: a genetic algorithm with targeted repair

Every timetable is made by a **genetic algorithm (GA)**. It keeps a population of candidate timetables (100
by default) and improves them generation after generation:

1. **Start**: make 100 different timetables, mostly random, some built greedily.
2. **Score**: give each a penalty (1000 points per clash plus a few points per broken wish).
3. **Choose parents**: pick better timetables to breed from (tournaments of 3).
4. **Mix**: combine two parents into two children, class by class.
5. **Mutate**: move a few classes at random, so the search keeps exploring.
6. **Repair**: move every clashing class to the best free time and room (the targeted repair).
7. **Keep the best**: the 2 best timetables pass unchanged into the next generation.

Steps 2 to 7 repeat until the best timetable has no clashes and has not improved for 100 generations, or
after at most 2000 generations, or when you press Stop. The run page shows this live: the generation, how many
timetables have been scored, the best and the average penalty of the population, and a chart. The result page
says how the GA found the timetable (generations, timetables scored, the first generation with no clashes,
and the seed that repeats it).

## Month view, dates and holidays

The genetic algorithm makes one week that repeats all semester. To see it on real dates, an administrator sets
the first day of teaching and how long teaching lasts, either **until a date** or **for a number of weeks** (for example 13;
the last day is worked out for you), and the days without teaching (public holidays, breaks) under *Manage, Semesters, Dates
and holidays*. You can also give the dates when you create a semester. The page then shows the length, for example "13 weeks,
64 teaching days". Then every timetable, in the app and on the public page, has a **Week / Month**
switch: the month view shows each weekday's classes on its date, marks holidays with their reason, and shades
days outside the semester. **Export with dates (CSV)** lists every class on its real date for the whole
semester, holidays left out. Holidays do not move classes; they only remove those days from the calendar. The dates are
optional; to remove them, empty the fields and save.

## Importing from a spreadsheet

*School data, Import from spreadsheet.* Download a template, fill it in, choose **File, Save As, CSV UTF-8**
in Excel and upload it. Import levels, rooms and lecturers first, then courses and availability. If any row
has a problem, nothing is saved and every problem is listed with its row number. Records with the same code
or staff number are updated, not duplicated.

| File | Columns |
| --- | --- |
| groups.csv (levels) | code, name, size |
| rooms.csv | code, name, capacity, room_type (lecture or lab) |
| lecturers.csv | staff_no, title, full_name, short_name, email |
| courses.csv | code, title, credit_units, hours_per_week, needs_lab, allow_two_hour_block, class_size_override, group_code, lecturer_staff_no |
| availability.csv | staff_no, day (Mon to Fri), start_time (like 08:00), status (available, preferred, unavailable) |

## Rules: what the timetable must and should do

Hard rules must all be met for a usable timetable. A break of a hard rule is a **clash**.

| Code | Rule |
| --- | --- |
| H1 | A lecturer is in only one place at a time |
| H2 | A room hosts only one class at a time |
| H3 | A level has only one class at a time |
| H4 | The room has enough seats for the class |
| H5 | Practicals are held in labs |
| H6 | No class in a blocked period, when its lecturer is unavailable, or running past the end of the day |

Soft rules describe a good timetable. The system keeps them as low as it can:

| Code | Wish |
| --- | --- |
| S1 | Lecturers teach in their preferred periods |
| S2 | Levels have no idle gaps between classes |
| S3 | A course's classes are on different days |
| S4 | No more than three hours of classes in a row |
| S5 | Avoid the last period of the day |

**Penalty** = 1000 x clashes + the weighted soft rule breaks. Lower is better. **Fitness** = 1 / (1 + penalty),
so a perfect timetable has fitness 1. Administrators can change the weights under *Manage, Rules and
weights*; keep the hard weight far larger than any soft weight.

## Results, comparing and publishing

- **Runs and results** lists every run of the semester. Open one for the verdict, the scores and the grids.
  When clashes remain, each cause is listed with a link to the lecturer, room or level concerned, and clash
  cells are marked **Clash** in red.
- **Quality report**: every rule break, one per line, with names and times.
- **Compare**: tick two or more finished runs and press **Compare ticked runs**.
- **Import a manual timetable** scores your hand-made timetable with exactly the same rules
  (columns `course_code, session_no, day, start_time, room_code`, one row per class).
- **Publish** makes a run the timetable everyone sees. It replaces the previous one at once; the public
  link stays the same. **Published timetable and share link** shows the link with a copy button.
- **Export CSV** gives a spreadsheet; **Print view** gives one level (or lecturer, or room) per A4 page,
  always in light colours.

## Search settings (advanced)

The usual values suit a department of about 40 courses. Each run can change them under *Advanced search
settings* on the Generate page; administrators set the defaults under *Manage, School settings*.

| Setting | Meaning | Default |
| --- | --- | --- |
| Population size | Candidate timetables improved together | 100 |
| Maximum generations | Upper limit on the run length | 2000 |
| Stop after this many generations without improvement | Once clash-free | 100 |
| Tournament size | Candidates compared when choosing a parent | 3 |
| Crossover rate | Chance two parents mix their classes | 0.8 |
| Mutation rate | Chance each class moves at random | 0.05 |
| Elite count | Best timetables kept unchanged | 2 |
| Greedy share | Part of the first timetables built greedily | 0.2 |
| Maximum repair moves | Classes the repair step may move per timetable | 30 |
| Random seed | Same seed and data give the same timetable; blank chooses one | blank |

Untick **Use targeted repair** only to compare with the standard search.

## Managing the school (owners and administrators)

- **Members and invitations**: invite people by email with a role; change roles; remove members (they keep
  their account for other schools). Invitations work for 7 days and can be sent again or withdrawn. Without email,
  use **Add someone without email**: enter their name, email and role (administrator, timetable officer or viewer),
  note the temporary password and give it to them in person. At their first sign-in they must choose their own.
  The list shows who has two-step sign-in on and who has not yet chosen a password.
- **Semesters**: create the next semester, set it active, and copy last semester's courses into it.
- **Activity log**: every sign-in, data change, run, publication and settings change in the school.
- **School settings**: the public link name, the default search settings, deleting old runs (published runs
  are kept), and for the owner, handing the school over or deleting it.

## Your account

Click your name in the top bar for **Profile and theme** (System, Light or Dark), **Change password** and
**Restart the tour**.

- **Keep me signed in.** Ticked on the sign-in page, it keeps you signed in on that browser for up to 30 days, until
  you press Sign out. Without it you are signed out after 60 minutes without activity. Untick it on shared or public
  computers. *Profile, Where you stay signed in* lists your browsers and has **Sign out on all browsers**. Changing
  your password signs out the other browsers.
- **Two-step sign-in.** Under *Profile, Two-step sign-in*: install an authenticator app (Google Authenticator,
  Microsoft Authenticator, Authy), scan the QR code (or type the key), and confirm with a code and your password. From
  then on, signing in asks for the 6-digit code after your password. Keep the 10 recovery codes you are shown; each
  lets you sign in once without your phone. It is optional but encouraged, especially for owners and administrators,
  who see a reminder on the dashboard until they set it up or hide it. If you lose both the phone and
  the codes, the platform administrator can turn it off for you (`php bin/reset_2fa.php --email=...`). Forgot your password? Use "Forgot your password?" on the sign-in page; the emailed
link works for 1 hour.

## Experiments for the dissertation (command line)

```
php bin/experiment.php --school=demo --label=SGA --repair=0 --seeds=1001-1030
php bin/experiment.php --school=demo --label=RGA --repair=1 --seeds=1001-1030
php bin/experiment.php --school=demo --label=POP50 --repair=1 --population=50 --seeds=2001-2010
php bin/export_results.php --school=demo --label=SGA
php bin/stats.php --school=demo --a=SGA --b=RGA --man --csv
```

Use the case-study school's link name for `--school` (it can be left out if there is only one school).
Exports go to `storage/exports`; each starts with lines recording the CPU, RAM, operating system, PHP
version and date. An interrupted batch can be restarted with the same command; finished seeds are skipped.
Experiment runs wait for a free slot, so they never share the processor with each other.

## When something goes wrong

Every error message says what to do next. Common cases:

- **"This form has expired"**: the page was open too long. Go back, refresh and try again.
- **Run stays "Waiting to start"**: runs of all schools take turns (the page shows your place). If it does
  not move for several minutes, ask the platform administrator to check the Health page.
- **Check readiness lists problems**: follow the button beside each one, then check again.
- **"Not allowed"**: your role cannot open that page; ask an owner or administrator of the school.
- **Signed out**: sessions end after 60 minutes without activity.
