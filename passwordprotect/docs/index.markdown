
Password Protect Extension
==========================

This is an extension that adds Password Protection to certain pages or entries, categories, chapters, weblogs
 or the complete site.  
If enabled the visitor will have to give the password, otherwise he/she will not see the web page.  
Passwords are case-sensitive, but the *used username is irrelevant*.  
You can also select to allow access (without giving the password) to users that are already 
logged into PivotX.  

In the configuration you can select what message is shown in the password popup and what message
is shown on the no access template.  

When weblog protection is used you can specify what weblog to use when displaying the no access message.  

If you use this extension for specific entries or pages, it's good practice to let the visitor know that they
will be asked for a password, when they click a link.  
Currently this can't be done for protected categories, chapters or weblogs.  
For an entry you can code inside your `[[subweblog]]` the following:

    [[if $entry.extrafields.passwordprotect!=""]]
        <strong>Note:</strong> This entry is password protected.
    [[/if]]
 
**Note:** Do not use this extension for really secret stuff. The content of the
page or entry is still stored in plain text in the database, so a very dedicated
cracker might still find a way to get the information. Compare it to an ordinary
bikelock: It will prevent people from taking your bike, but a professional will
have the tools to break it open, and steal the bike anyway.  

**Note 2:** The specified no access template will be shown without the automatic additions that are
done for normal templates. So if for example your no access template needs the jQuery library added to
the header you will have to add it yourself. This should be temporary (we are working on that).
