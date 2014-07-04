--[[
hosts parser module

@author kim https://github.com/xqpmjh
@version 1.0.0
]]

local cjson                 = require "cjson"
local dict                  = require "lib.dict"
local g                     = require "lib.g"

local io                    = io
local string                = string
local type                  = type
local next                  = next
local pairs                 = pairs
local ipairs                = ipairs
local tonumber              = tonumber
local setmetatable          = setmetatable

local ngx                   = ngx
local header                = ngx.header

local log                   = g.log

--[[ init module ]]
module(...)
_VERSION = '1.0.0'

--[[ indexed by current module env. ]]
local mt = {__index = _M}

--[[-------------------------------------------------------------------------]]

--[[ instantiation ]]
function new(self)
    return setmetatable({
        HOSTS_FILE_PATH         = '/etc/hosts',

        LCACHE_HOSTS_KEY        = 'key_etc_hosts',
        LCACHE_HOSTS_EXPIRES    = 5
    }, mt)
end

--[[
switch host to ip address by /etc/hosts file
@param string host
@return string
]]
function parse(self, host)
    local rs = host
    if type(host) == 'string' then
        -- check if host is a "real" host address
        if not host:match("(%d+)%.(%d+)%.(%d+)%.(%d+)") then

            -- try get cache
            local etchosts
            local dkey = self.LCACHE_HOSTS_KEY
            local lcache = self:_getDict('lcache')
            if lcache then
                if ngx.var.arg_dy == 'c' then
                    lcache:delete(dkey)
                else
                    etchosts = lcache:get(dkey)
                    if not g.empty(etchosts) then
                        etchosts = cjson.decode(etchosts)
                    end
                end
            end

            -- try parse file
            local hostfile = self.HOSTS_FILE_PATH
            if not etchosts then
                local hf, err = io.open(hostfile, 'r')
                header['-hfopn'] = 1
                if not hf then
                    log('failed open host file: ' .. hostfile .. ' - ' .. (err and err or ''))
                else
                    etchosts = {}
                    for ln in hf:lines() do
                        etchosts[#etchosts + 1] = ln
                    end
                    hf:close()
                    lcache:set(self.LCACHE_HOSTS_KEY, cjson.encode(etchosts), self.LCACHE_HOSTS_EXPIRES)
                end
            end

            -- try find host/ip matches
            if type(etchosts) == 'table' then
                local hit = false
                for _, line in ipairs(etchosts) do
                    if string.find(line, host) then
                        local h = g.explode("%S+", line)
                        if type(h) == 'table' and h[1] then
                            rs = h[1]
                            hit = true
                        end
                        break
                    end
                end
                if not hit then
                    log('missing host: ' .. host .. ' in ' .. hostfile)
                end
            end

        end
    end
    return rs
end

--[[
get local cache object
]]
function _getDict(self, dictionary)
    if not self.dict then
        self.dict = dict:new(dictionary)
    end
    return self.dict
end

--[[-------------------------------------------------------------------------]]

--[[ to prevent use of casual module global variables ]]
setmetatable(_M, {
    __newindex = function (table, key, val)
        log('attempt to write to undeclared variable "' .. key .. '" in ' .. table._NAME)
    end
})


