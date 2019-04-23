# Zipdev, Cloudbeds test

# Requirements
1. User is able to specify price depending on the dates. i.e. set the price $x for the
dates range $startDate - $endDate. Let’s call such ranges as intervals.
2. User can add as many intervals as he wants (using any start/end date).
3. System can’t have crossing intervals.
4. New interval price have higher priority over existing ones.
5. New interval can’t lead to changes in dates not belonged to its dates range.
6. If user tries to save interval that interferes with existing ones in DB, system has to
apply last user changes and modify other intervals in order to apply requirement
3.
7. Any intervals with the same price that can be merged (without gaps between)
should be merged.

# DB
You need to develop a system for saving prices in intervals. Each interval should have
such fields:
● date_start (date)
● date_end (date)
● price (float)

# What you need to do:
● Develop DB structure (add MySQL dump file to the code)
● Implement 3 API methods to insert, update and delete intervals
● Provide a simple interface which will show all intervals sorted by date_start and
that will allow performing all CRUD operations
● Add a feature to clear all DB to start testing from the scratch
● Unit tests for what you consider to be necessary
● As minimum queries to DB as possible
● Don’t use any frameworks
● Well documented code
● Code must be allocated on Github (please provide the link)
● Application should be deployed to some server, ready for manual testing. URL
should be provided. (it needs to speed-up the reviewing process)
