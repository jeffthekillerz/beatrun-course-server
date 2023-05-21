# beatrun course server
<p>
simple beatrun course server recreation. <br><br>
_internal.json contains auth keys. leave it at {} to allow anyone to upload or get courses. keep in mind 0 is the default apikey in beatrun <br>
_ratelimit.json contains ip's for ratelimitage <br>
hide both of these files from being accessed by the public using settings in nginx or apache or whatever <br><br>
to use a custom course server, modify OnlineCourse.lua found in beatrun/gamemodes/gamemode/cl/ and replace all datae.org mentions to whatever custom server you have.
</p>