
local results, cursor, total, command = {}, 0, 0, ARGV[1]

if redis.REDIS_VERSION == nil then
    redis.replicate_commands()
end

for i, pattern in ipairs(KEYS) do
    repeat
        results = redis.call('SCAN', cursor, 'MATCH', pattern, 'COUNT', 100)

        if #results > 1 and #results[2] > 0 then
            redis.call(command, unpack(results[2]))
            total = total + #results[2]
        end

        cursor = tonumber(results[1])
    until cursor == 0
end

return total
