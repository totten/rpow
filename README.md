# CiviCRM Replay-on-Write Helper (civirpow)

<img src="https://static1.squarespace.com/static/56b8f8efab48debb2efb2ef5/t/5a2938fb24a6942a95c17ec6/1512651016911/Batman+and+Robin+Bif+Pow.jpg?format=500w"  />

This is a small utility which allows CiviCRM to work with an opportunistic
combination of a read-write master database (RWDB) and read-only slave
databases (RODB).  The general idea is to connect to RODB optimistically
(expecting a typical read-only use-case) -- and then switch to RWDB *if*
there is an actual write.

* [about.md](doc/about.md): More detailed discussion of the design
* [install.md](doc/install.md): Installation (non-development)
* [develop.md](doc/develop.md): Installation and experimentation (development)
