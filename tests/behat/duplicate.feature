@mod @mod_book @booktool @booktool_duplicate @javascript
Feature: Duplicate book chapters and sub-chapters
  In order to quickly reuse book content
  As a teacher
  I need to duplicate chapters and sub-chapters from the table of contents.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name      | course | idnumber |
      | book     | Test book | C1     | book1    |
    And the following "mod_book > chapters" exist:
      | book      | title              | content                | pagenum | subchapter |
      | Test book | Basic chapter      | Plain basic content    | 1       | 0          |
      | Test book | Chapter with image | See embedded image     | 2       | 0          |
      | Test book | Parent A           | Parent A content       | 3       | 0          |
      | Test book | Sub A1             | Sub A1 content         | 4       | 1          |
      | Test book | Sub A2             | Sub A2 content         | 5       | 1          |
      | Test book | Parent B           | Parent B content       | 6       | 0          |
      | Test book | Sub B1             | Sub B1 content         | 7       | 1          |
      | Test book | Sub B2             | Sub B2 content         | 8       | 1          |
      | Test book | Sub B3             | Sub B3 content         | 9       | 1          |
      | Test book | Parent C           | Parent C content       | 10      | 0          |
      | Test book | Sub C1             | Sub C1 content         | 11      | 1          |
      | Test book | Sub C2             | Sub C2 content         | 12      | 1          |
    And I log in as "teacher1"
    And I change the window size to "large"

  Scenario: Duplicate a basic chapter
    Given I am on the "Test book" "book activity" page
    And I turn editing mode on
    And I should see "1. Basic chapter" in the "Table of contents" "block"
    And I should see "2. Chapter with image" in the "Table of contents" "block"
    When I click on ".booktool_duplicate-action" "css_element" in the "1. Basic chapter" "list_item"
    Then I should see "Chapter \"Basic chapter\" duplicated."
    And I should see "1. Basic chapter" in the "Table of contents" "block"
    And I should see "2. Copy of Basic chapter" in the "Table of contents" "block"
    And I should see "3. Chapter with image" in the "Table of contents" "block"
    And I should see "Plain basic content"

  Scenario: Duplicate a chapter that contains an embedded file
    Given the book chapter "Chapter with image" in "Test book" has an embedded image "mod/lesson/tests/fixtures/moodle_logo.jpg"
    And I am on the "Test book" "book activity" page
    And I turn editing mode on
    And I follow "Chapter with image"
    And "//div[contains(@class, 'book_content')]//img[contains(@src, 'moodle_logo.jpg')]" "xpath_element" should exist
    When I click on ".booktool_duplicate-action" "css_element" in the "2. Chapter with image" "list_item"
    Then I should see "Chapter \"Chapter with image\" duplicated."
    And I should see "3. Copy of Chapter with image" in the "Table of contents" "block"
    And "//div[contains(@class, 'book_content')]//img[contains(@src, 'moodle_logo.jpg')]" "xpath_element" should exist

  Scenario: Duplicate a chapter that has sub-chapters
    Given I am on the "Test book" "book activity" page
    And I turn editing mode on
    And I should see "3. Parent A" in the "Table of contents" "block"
    And I should see "3.1. Sub A1" in the "Table of contents" "block"
    And I should see "3.2. Sub A2" in the "Table of contents" "block"
    And I should see "4. Parent B" in the "Table of contents" "block"
    When I click on ".booktool_duplicate-action" "css_element" in the "3. Parent A" "list_item"
    Then I should see "Chapter \"Parent A\" duplicated with 2 subchapter(s)."
    And I should see "3. Parent A" in the "Table of contents" "block"
    And I should see "3.1. Sub A1" in the "Table of contents" "block"
    And I should see "3.2. Sub A2" in the "Table of contents" "block"
    And I should see "4. Copy of Parent A" in the "Table of contents" "block"
    And I should see "4.1. Sub A1" in the "Table of contents" "block"
    And I should see "4.2. Sub A2" in the "Table of contents" "block"
    And I should see "5. Parent B" in the "Table of contents" "block"
    And I should see "5.1. Sub B1" in the "Table of contents" "block"
    And I should see "5.2. Sub B2" in the "Table of contents" "block"
    And I should see "5.3. Sub B3" in the "Table of contents" "block"

  Scenario: Duplicate a sub-chapter only
    Given I am on the "Test book" "book activity" page
    And I turn editing mode on
    And I should see "4. Parent B" in the "Table of contents" "block"
    And I should see "4.1. Sub B1" in the "Table of contents" "block"
    And I should see "4.2. Sub B2" in the "Table of contents" "block"
    And I should see "4.3. Sub B3" in the "Table of contents" "block"
    When I click on ".booktool_duplicate-action" "css_element" in the "4.1. Sub B1" "list_item"
    Then I should see "Chapter \"Sub B1\" duplicated."
    And I should see "4. Parent B" in the "Table of contents" "block"
    And I should see "4.1. Sub B1" in the "Table of contents" "block"
    And I should see "4.2. Copy of Sub B1" in the "Table of contents" "block"
    And I should see "4.3. Sub B2" in the "Table of contents" "block"
    And I should see "4.4. Sub B3" in the "Table of contents" "block"
    And I should see "5. Parent C" in the "Table of contents" "block"
